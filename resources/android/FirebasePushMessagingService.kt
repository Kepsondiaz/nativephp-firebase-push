package com.kepson.firebasepush

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject
import java.time.Instant

/**
 * DRAFT — NOT YET VERIFIED ON A DEVICE.
 *
 * Receives FCM messages and forwards them to the PHP layer using the same
 * native→PHP dispatch mechanism as the NativePHP core plugins:
 *   NativeActionCoordinator.dispatchEvent("Fully\\Qualified\\PhpEvent", json)
 * The runtime maps top-level JSON keys to the PHP event's constructor params;
 * our bridge events take a single `array $payload`, so the JSON is wrapped as
 * { "payload": { ...notification fields... } }.
 *
 * OPEN QUESTIONS to resolve when building against a real host app:
 *  1. The free nativephp/mobile base already registers its own
 *     FirebaseMessagingService for token generation (TokenGenerated). FCM
 *     delivers to a single service, so registering this one may shadow the
 *     base's. Confirm whether we must also forward onNewToken here, or hook the
 *     base's service instead.
 *  2. Confirm the exact `dispatchEvent` accessor (the camera coordinator calls
 *     it unqualified; it originates from
 *     com.nativephp.mobile.utils.NativeActionCoordinator).
 *  3. Channel values are hardcoded defaults here. Injecting the PHP
 *     firebase-push.android.* config into native requires an init bridge
 *     function (future work).
 */
class FirebasePushMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        // See open question #1: the base owns TokenGenerated. If this service
        // shadows the base's, forward the token to PHP here.
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val payload = buildPayload(message)

        ensureChannel()
        showNotification(message, payload)

        dispatch(EVENT_RECEIVED, payload)
    }

    private fun buildPayload(message: RemoteMessage): JSONObject {
        val data = JSONObject()
        for ((key, value) in message.data) {
            data.put(key, value)
        }

        return JSONObject().apply {
            put("id", message.messageId ?: "")
            message.notification?.let { n ->
                n.title?.let { put("title", it) }
                n.body?.let { put("body", it) }
                n.imageUrl?.let { put("imageUrl", it.toString()) }
                n.channelId?.let { put("channel", it) }
            }
            (message.data["link"] ?: message.data["url"])?.let { put("link", it) }
            message.collapseKey?.let { put("collapseKey", it) }
            put("sentAt", Instant.ofEpochMilli(message.sentTime).toString())
            put("data", data)
        }
    }

    private fun dispatch(eventClass: String, payload: JSONObject) {
        val envelope = JSONObject().put("payload", payload)
        NativeActionCoordinator.dispatchEvent(eventClass, envelope.toString())
    }

    private fun ensureChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return
        }

        val channel = NotificationChannel(CHANNEL_ID, CHANNEL_NAME, NotificationManager.IMPORTANCE_HIGH)
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.createNotificationChannel(channel)
    }

    private fun showNotification(message: RemoteMessage, payload: JSONObject) {
        val notification = message.notification ?: return

        val tapIntent = Intent(this, FirebasePushTapActivity::class.java).apply {
            putExtra(FirebasePushTapActivity.EXTRA_PAYLOAD, payload.toString())
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }

        val pendingIntent = PendingIntent.getActivity(
            this,
            message.messageId?.hashCode() ?: 0,
            tapIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val builder = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(notification.title)
            .setContentText(notification.body)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)

        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.notify(message.messageId?.hashCode() ?: 0, builder.build())
    }

    companion object {
        const val CHANNEL_ID = "default"
        const val CHANNEL_NAME = "Notifications"
        const val EVENT_RECEIVED =
            "Kepson\\NativePhpFirebasePush\\Bridge\\Events\\NativeNotificationReceived"
    }
}
