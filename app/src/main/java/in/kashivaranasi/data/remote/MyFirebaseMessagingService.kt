package `in`.kashivaranasi.data.remote

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import `in`.kashivaranasi.MainActivity
import `in`.kashivaranasi.R
import `in`.kashivaranasi.data.repository.TourRepository
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import javax.inject.Inject

@AndroidEntryPoint
class MyFirebaseMessagingService : FirebaseMessagingService() {

    @Inject
    lateinit var repository: TourRepository

    private val job = SupervisorJob()
    private val scope = CoroutineScope(Dispatchers.IO + job)

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        val prefs = getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
        prefs.edit().putString("fcm_token", token).apply()
        
        val userId = prefs.getInt("user_id", -1)
        if (userId != -1) {
            updateTokenOnServer(userId, token)
        }
    }

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        
        val data = remoteMessage.data
        val type = data["type"]
        val alertId = data["alert_id"]

        // भाषा के अनुसार सही मैसेज चुनें
        val title: String
        val body: String

        if (type == "wakeup") {
            // सर्वर के मैसेज को नज़रअंदाज़ करें और ऐप के अनुवादित मैसेज दिखाएं
            title = getString(R.string.wakeup_alert_title)
            body = getString(R.string.wakeup_alert_body)
        } else {
            title = data["title"] ?: remoteMessage.notification?.title ?: getString(R.string.app_name)
            body = data["body"] ?: remoteMessage.notification?.body ?: ""
        }

        // पावती (Acknowledgement) भेजें
        val prefs = getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
        val userId = prefs.getInt("user_id", -1)
        if (userId != -1 && alertId != null) {
            scope.launch {
                try {
                    repository.acknowledgeAlert(userId, alertId)
                } catch (e: Exception) {
                    Log.e("FCM", "Failed to acknowledge", e)
                }
            }
        }

        sendNotification(title, body)
    }

    private fun sendNotification(title: String, messageBody: String) {
        val intent = Intent(this, MainActivity::class.java)
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_ONE_SHOT
        )

        val channelId = "wakeup_urgent_v4"
        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE) 
        
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                getString(R.string.perm_notif_title),
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                enableLights(true)
                enableVibration(true)
                val audioAttributes = AudioAttributes.Builder()
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .setUsage(AudioAttributes.USAGE_ALARM)
                    .build()
                setSound(soundUri, audioAttributes)
            }
            notificationManager.createNotificationChannel(channel)
        }

        val notificationBuilder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(android.R.drawable.ic_lock_idle_alarm)
            .setContentTitle(title)
            .setContentText(messageBody)
            .setAutoCancel(true)
            .setSound(soundUri)
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_ALARM)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setContentIntent(pendingIntent)

        notificationManager.notify(System.currentTimeMillis().toInt(), notificationBuilder.build())
    }

    private fun updateTokenOnServer(userId: Int, token: String) {
        scope.launch {
            try {
                repository.updateFcmToken(userId, token)
            } catch (e: Exception) {}
        }
    }

    override fun onDestroy() {
        job.cancel()
        super.onDestroy()
    }
}
