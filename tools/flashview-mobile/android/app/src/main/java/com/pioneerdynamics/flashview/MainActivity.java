package com.pioneerdynamics.flashview;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import com.getcapacitor.BridgeActivity;
import org.json.JSONException;
import org.json.JSONObject;

public class MainActivity extends BridgeActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // Cold start: store text so JS can read it after the WebView finishes loading.
        storeShareText(getIntent());
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        storeShareText(intent);
        // Warm start: the bridge is live — pass the text directly via a JS event
        // so the JS side never needs to read (and potentially misparse) SharedPreferences.
        deliverShareTextViaEvent(intent);
    }

    private void storeShareText(Intent intent) {
        String text = extractText(intent);
        if (text == null) {
            return;
        }
        // Write using the same SharedPreferences file that @capacitor/preferences uses.
        SharedPreferences prefs = getSharedPreferences("CapacitorStorage", MODE_PRIVATE);
        prefs.edit().putString("flashview_pending_share", text).apply();
    }

    private void deliverShareTextViaEvent(Intent intent) {
        String text = extractText(intent);
        if (text == null || getBridge() == null) {
            return;
        }
        try {
            String payload = new JSONObject().put("text", text).toString();
            getBridge().triggerJSEvent("shareIntentReceived", "window", payload);
        } catch (JSONException ignored) {
        }
    }

    private String extractText(Intent intent) {
        if (!Intent.ACTION_SEND.equals(intent.getAction())) {
            return null;
        }
        if (!"text/plain".equals(intent.getType())) {
            return null;
        }
        // getCharSequenceExtra handles SpannableString and other CharSequence subclasses
        // that some apps pass instead of a plain String.
        CharSequence charSeq = intent.getCharSequenceExtra(Intent.EXTRA_TEXT);
        if (charSeq == null) {
            return null;
        }
        String text = charSeq.toString().trim();
        return text.isEmpty() ? null : text;
    }
}
