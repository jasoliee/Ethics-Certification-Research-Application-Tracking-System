<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Preview</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 24px; color: #172033; background: #f4f7f5; font-family: Arial, sans-serif; }
        main { width: min(520px, 100%); border: 1px solid #d8e2dc; border-radius: 8px; padding: 28px; text-align: center; background: #fff; }
        h1 { margin: 0 0 8px; font-size: 22px; }
        p { margin: 0; color: #596579; line-height: 1.55; overflow-wrap: anywhere; }
        .file { margin-top: 8px; color: #172033; font-weight: 700; }
        a { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; margin-top: 22px; border-radius: 6px; padding: 10px 18px; color: #fff; background: #16854d; font-weight: 700; text-decoration: none; }
        a:focus-visible { outline: 3px solid #9dd6b7; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <h1>Preview unavailable in this browser</h1>
        <p class="file">{{ $document->original_file_name }}</p>
        <p>Word and Excel rendering depends on browser support. The original file remains private and is available through the authorized download.</p>
        <a href="{{ $downloadUrl }}">Download Document</a>
    </main>
</body>
</html>
