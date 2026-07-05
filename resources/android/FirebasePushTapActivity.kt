package com.kepson.firebasepush

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject

/**
 * DRAFT — NOT YET VERIFIED ON A DEVICE.
 *
 * Trampoline activity launched when the user taps a notification shown by
 * FirebasePushMessagingService. It forwards the tap (with any deep-link target
 * in the payload) to PHP via the NativeNotificationTapped bridge event, then
 * hands off to the host app's launcher activity so the app opens/resumes.
 *
 * OPEN QUESTIONS to resolve against a real host app:
 *  1. The correct way to resume the NativePHP host activity (class name / intent)
 *     — here we fall back to the package launch intent.
 *  2. Background/killed-state notification messages displayed by the system
 *     (not by our service) tap straight to the launcher and will not pass
 *     through this activity; capturing those requires reading the launch intent
 *     in the host activity. Out of scope for this draft.
 */
class FirebasePushTapActivity : Activity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val payloadJson = intent.getStringExtra(EXTRA_PAYLOAD)
        if (payloadJson != null) {
            val envelope = JSONObject().put("payload", JSONObject(payloadJson))
            NativeActionCoordinator.dispatchEvent(EVENT_TAPPED, envelope.toString())
        }

        packageManager.getLaunchIntentForPackage(packageName)?.let { launch ->
            launch.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
            startActivity(launch)
        }

        finish()
    }

    companion object {
        const val EXTRA_PAYLOAD = "firebase_push_payload"
        const val EVENT_TAPPED =
            "Kepson\\NativePhpFirebasePush\\Bridge\\Events\\NativeNotificationTapped"
    }
}
