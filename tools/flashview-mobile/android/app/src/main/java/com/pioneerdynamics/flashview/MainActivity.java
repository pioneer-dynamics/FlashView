package com.pioneerdynamics.flashview;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        handleShareIntent(getIntent());
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleShareIntent(intent);
        // Notify the JS side that a new share arrived while the app was already open.
        if (getBridge() != null) {
            getBridge().triggerJSEvent("shareIntentReceived", "window");
        }
    }

    private void handleShareIntent(Intent intent) {
        if (!Intent.ACTION_SEND.equals(intent.getAction())) {
            return;
        }
        if (!"text/plain".equals(intent.getType())) {
            return;
        }
        String text = intent.getStringExtra(Intent.EXTRA_TEXT);
        if (text == null || text.isEmpty()) {
            return;
        }
        // Store using the same key as @capacitor/preferences so JS can read it.
        SharedPreferences prefs = getSharedPreferences("CapacitorStorage", MODE_PRIVATE);
        prefs.edit().putString("flashview_pending_share", text).apply();
    }
}
