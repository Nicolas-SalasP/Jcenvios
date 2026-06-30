package cl.jcenvios.twa

import android.net.Uri
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.browser.customtabs.CustomTabColorSchemeParams
import androidx.browser.customtabs.CustomTabsIntent
import androidx.core.content.ContextCompat

class LauncherActivity : AppCompatActivity() {

    companion object {
        private const val TWA_URL = "https://jcenvios.cl/dashboard/"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val colorSchemeParams = CustomTabColorSchemeParams.Builder()
            .setToolbarColor(ContextCompat.getColor(this, R.color.brand_primary))
            .build()

        val intent = CustomTabsIntent.Builder()
            .setDefaultColorSchemeParams(colorSchemeParams)
            .setShowTitle(false)
            .build()

        intent.launchUrl(this, Uri.parse(TWA_URL))
        finish()
    }
}
