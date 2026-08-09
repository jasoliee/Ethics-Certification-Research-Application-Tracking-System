<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->fileTypeLabel() }} Preview</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: clamp(16px, 4vw, 32px); color: #172033; background: #f4f7f5; font-family: Arial, sans-serif; }
        main { width: min(560px, 100%); border: 1px solid #d8e2dc; border-radius: 10px; padding: clamp(24px, 5vw, 36px); text-align: center; background: #fff; box-shadow: 0 8px 26px rgb(28 66 45 / 8%); }
        .type { display: inline-flex; margin-bottom: 16px; border-radius: 999px; padding: 7px 12px; color: #0b6b3d; background: #eaf6ef; font-size: 13px; font-weight: 700; }
        h1 { margin: 0 0 8px; font-size: 22px; }
        p { margin: 0; color: #596579; line-height: 1.55; overflow-wrap: anywhere; }
        .file { margin: 8px 0 14px; color: #172033; font-weight: 700; }
        a { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; margin-top: 22px; border-radius: 6px; padding: 10px 18px; color: #fff; background: #16854d; font-weight: 700; text-decoration: none; }
        a:focus-visible { outline: 3px solid #9dd6b7; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <span class="type">{{ $document->fileTypeLabel() }}</span>
        <h1>Secure inline preview unavailable</h1>
        <p class="file">{{ $document->original_file_name }}</p>
        @if ($document->previewKind() === 'office')
            <p>ECRATS cannot render this Office format safely on this server. The file remains private; download it through the authorized route and open it in Microsoft Office or another trusted local application.</p>
        @else
            <p>This format cannot be rendered safely in the browser. The original file remains private and is available through the authorized download.</p>
        @endif
        <a href="{{ $downloadUrl }}">Download {{ $document->fileTypeLabel() }}</a>
    </main>
</body>
</html>
