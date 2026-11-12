<!DOCTYPE html>
<html>
<body style="font-family: system-ui, sans-serif; color: #1c1917;">
    <p>Your login code for {{ config('app.name') }}:</p>
    <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px; font-family: monospace;">{{ $code }}</p>
    <p>It works once and expires in {{ $minutes }} minutes. Or sign in with one click:</p>
    <p><a href="{{ $link }}">Sign in to {{ config('app.name') }}</a></p>
    <p style="color: #78716c; font-size: 13px;">Didn't ask for this? You can ignore this e-mail; nothing happens without the code.</p>
</body>
</html>
