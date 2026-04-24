package com.pioneerdynamics.flashview;

import android.Manifest;
import android.content.ContentResolver;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.OpenableColumns;
import android.util.Base64;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.getcapacitor.BridgeActivity;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.util.ArrayList;
import java.util.List;

public class MainActivity extends BridgeActivity {

    private static final int MAX_FILE_BYTES = 25 * 1024 * 1024; // 25 MB
    private static final int PERMISSION_REQUEST_CODE = 1001;

    /**
     * MediaStore share intent held for a permission-grant retry.
     * FileProvider URIs (PDFs, etc.) are processed immediately without needing this.
     */
    private Intent pendingPermissionRetry = null;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        Intent intent = getIntent();
        // Always attempt to process immediately — works for FileProvider URIs and
        // already-permissioned MediaStore URIs without blocking on a dialog.
        storeShareIntent(intent);
        // For MediaStore file shares that need permissions, request them and retry
        // after grant so the share isn't silently dropped on first install.
        if (isMediaStoreFileShare(intent) && !hasRequiredMediaPermissions()) {
            pendingPermissionRetry = intent;
            requestMediaPermissions();
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        storeShareIntent(intent);
        // Warm start: the bridge is live — deliver the payload directly via a JS event.
        deliverShareIntentViaEvent(intent);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == PERMISSION_REQUEST_CODE && pendingPermissionRetry != null) {
            // Re-process in case the first attempt failed due to missing permissions.
            storeShareIntent(pendingPermissionRetry);
            pendingPermissionRetry = null;
        }
    }

    // ── Permissions ─────────────────────────────────────────────────────────────

    private boolean isMediaStoreFileShare(Intent intent) {
        if (!Intent.ACTION_SEND.equals(intent.getAction())) {
            return false;
        }
        Uri stream = intent.getParcelableExtra(Intent.EXTRA_STREAM);
        if (stream == null) {
            return false;
        }
        String authority = stream.getAuthority();
        return authority != null && authority.startsWith("media");
    }

    private boolean hasRequiredMediaPermissions() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            return ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_IMAGES) == PackageManager.PERMISSION_GRANTED
                    && ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_AUDIO) == PackageManager.PERMISSION_GRANTED
                    && ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_VIDEO) == PackageManager.PERMISSION_GRANTED;
        } else {
            return ContextCompat.checkSelfPermission(this, Manifest.permission.READ_EXTERNAL_STORAGE) == PackageManager.PERMISSION_GRANTED;
        }
    }

    private void requestMediaPermissions() {
        List<String> needed = new ArrayList<>();
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_IMAGES) != PackageManager.PERMISSION_GRANTED) {
                needed.add(Manifest.permission.READ_MEDIA_IMAGES);
            }
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_AUDIO) != PackageManager.PERMISSION_GRANTED) {
                needed.add(Manifest.permission.READ_MEDIA_AUDIO);
            }
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_VIDEO) != PackageManager.PERMISSION_GRANTED) {
                needed.add(Manifest.permission.READ_MEDIA_VIDEO);
            }
        } else {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) {
                needed.add(Manifest.permission.READ_EXTERNAL_STORAGE);
            }
        }
        if (!needed.isEmpty()) {
            ActivityCompat.requestPermissions(this, needed.toArray(new String[0]), PERMISSION_REQUEST_CODE);
        }
    }

    // ── Dispatch ────────────────────────────────────────────────────────────────

    private void storeShareIntent(Intent intent) {
        if (!Intent.ACTION_SEND.equals(intent.getAction())) {
            return;
        }
        Uri stream = intent.getParcelableExtra(Intent.EXTRA_STREAM);
        if (stream != null) {
            storeSharedFile(intent, stream);
        } else {
            storeSharedText(intent);
        }
    }

    private void deliverShareIntentViaEvent(Intent intent) {
        if (!Intent.ACTION_SEND.equals(intent.getAction())) {
            return;
        }
        Uri stream = intent.getParcelableExtra(Intent.EXTRA_STREAM);
        if (stream != null) {
            deliverSharedFileViaEvent(intent, stream);
        } else {
            deliverSharedTextViaEvent(intent);
        }
    }

    // ── Text ────────────────────────────────────────────────────────────────────

    private void storeSharedText(Intent intent) {
        String text = extractText(intent);
        if (text == null) {
            return;
        }
        SharedPreferences prefs = getSharedPreferences("CapacitorStorage", MODE_PRIVATE);
        prefs.edit().putString("flashview_pending_share", text).apply();
    }

    private void deliverSharedTextViaEvent(Intent intent) {
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
        if (!"text/plain".equals(intent.getType())) {
            return null;
        }
        CharSequence charSeq = intent.getCharSequenceExtra(Intent.EXTRA_TEXT);
        if (charSeq == null) {
            return null;
        }
        String text = charSeq.toString().trim();
        return text.isEmpty() ? null : text;
    }

    // ── File ────────────────────────────────────────────────────────────────────

    private void storeSharedFile(Intent intent, Uri fileUri) {
        JSONObject fileJson = extractFileJson(intent, fileUri);
        if (fileJson == null) {
            return;
        }
        SharedPreferences prefs = getSharedPreferences("CapacitorStorage", MODE_PRIVATE);
        prefs.edit().putString("flashview_pending_share_file", fileJson.toString()).apply();
    }

    private void deliverSharedFileViaEvent(Intent intent, Uri fileUri) {
        if (getBridge() == null) {
            return;
        }
        JSONObject fileJson = extractFileJson(intent, fileUri);
        if (fileJson == null) {
            return;
        }
        try {
            JSONObject payload = new JSONObject().put("file", fileJson);
            getBridge().triggerJSEvent("shareIntentReceived", "window", payload.toString());
        } catch (JSONException ignored) {
        }
    }

    /**
     * Read the file at fileUri and return a JSON object with filename, mimeType, and base64 data.
     * Returns null if the file is unreadable or exceeds MAX_FILE_BYTES.
     */
    private JSONObject extractFileJson(Intent intent, Uri fileUri) {
        ContentResolver cr = getContentResolver();

        // Resolve MIME type
        String mimeType = intent.getType();
        if (mimeType == null || mimeType.isEmpty()) {
            mimeType = cr.getType(fileUri);
        }
        if (mimeType == null) {
            mimeType = "application/octet-stream";
        }

        // Resolve display name
        String filename = resolveFilename(cr, fileUri);

        // Read bytes
        byte[] bytes;
        try {
            bytes = readBytes(cr, fileUri);
        } catch (Exception e) {
            return null;
        }

        if (bytes == null || bytes.length > MAX_FILE_BYTES) {
            return null;
        }

        String base64 = Base64.encodeToString(bytes, Base64.NO_WRAP);

        try {
            return new JSONObject()
                    .put("filename", filename)
                    .put("mimeType", mimeType)
                    .put("size", bytes.length)
                    .put("data", base64);
        } catch (JSONException e) {
            return null;
        }
    }

    private String resolveFilename(ContentResolver cr, Uri uri) {
        try (Cursor cursor = cr.query(uri, null, null, null, null)) {
            if (cursor != null && cursor.moveToFirst()) {
                int idx = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME);
                if (idx >= 0) {
                    String name = cursor.getString(idx);
                    if (name != null && !name.isEmpty()) {
                        return name;
                    }
                }
            }
        } catch (Exception ignored) {
        }
        // Fall back to last path segment
        String path = uri.getLastPathSegment();
        return (path != null && !path.isEmpty()) ? path : "file";
    }

    private byte[] readBytes(ContentResolver cr, Uri uri) throws Exception {
        try (InputStream is = cr.openInputStream(uri);
             ByteArrayOutputStream baos = new ByteArrayOutputStream()) {
            if (is == null) {
                return null;
            }
            byte[] buffer = new byte[8192];
            int n;
            while ((n = is.read(buffer)) != -1) {
                baos.write(buffer, 0, n);
                if (baos.size() > MAX_FILE_BYTES) {
                    return null; // Too large
                }
            }
            return baos.toByteArray();
        }
    }
}
