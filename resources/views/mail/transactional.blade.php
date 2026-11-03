<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $messageData['title'] }}</title></head>
<body style="margin:0;background:#f4f5f7;color:#182840;font-family:Arial,Helvetica,sans-serif;line-height:1.6">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden">
<tr><td style="padding:24px 32px;background:#182840;color:#fff;font-size:28px;font-weight:bold">Pyesio<span style="color:#f89632">.</span></td></tr>
<tr><td style="padding:32px"><h1 style="font-size:24px;line-height:1.3;margin:0 0 20px">{{ $messageData['title'] }}</h1>
<p style="white-space:pre-line">{{ $messageData['body'] }}</p>
@if(!empty($messageData['action_url']))
<p style="margin:28px 0"><a href="{{ $messageData['action_url'] }}" style="display:inline-block;padding:12px 24px;background:#f89632;color:#182840;font-weight:bold;border-radius:8px;text-decoration:none">{{ $messageData['action_text'] }}</a></p>
<p style="font-size:12px;color:#536175;word-break:break-all">If the button does not work, copy this link into your browser:<br>{{ $messageData['action_url'] }}</p>
@endif
@if(!empty($messageData['note']))<p style="font-size:13px;color:#536175">{{ $messageData['note'] }}</p>@endif
</td></tr><tr><td style="padding:20px 32px;border-top:1px solid #e5e7eb;color:#536175;font-size:12px">Pyesio · Hardware business, made simple.<br>This is an automated account or service message.</td></tr>
</table></td></tr></table></body></html>
