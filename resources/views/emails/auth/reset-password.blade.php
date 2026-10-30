<!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>بازیابی رمز عبور بیلدینو</title></head>
<body dir="rtl" style="margin:0;padding:0;background-color:#eef3f8;color:#172f49;font-family:Tahoma,Arial,sans-serif;font-weight:400;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">درخواست بازبینی رمز عبور سامانه هوشمند مدیریت ساختمان بیلدینو</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef3f8;"><tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #dce5ee;border-radius:20px;overflow:hidden;">
<tr><td align="center" style="padding:20px 24px;background-color:#e8f1f8;background-image:radial-gradient(ellipse at top right,#d7e9f5,transparent 65%);border-bottom:1px solid #dce5ee;">
<img src="{{ $message->embedData(base64_decode(file_get_contents(resource_path('mail/buildino-logo.png.base64'))), 'buildino.png', 'image/png') }}" width="220" alt="بیلدینو — Buildino" style="display:block;width:220px;max-width:100%;height:auto;border:0;">
</td></tr>
<tr><td dir="rtl" align="right" style="padding:32px 28px;">
<h1 style="margin:0 0 18px;font-size:22px;line-height:1.8;font-weight:400;">شما درخواست بازبینی رمز عبور داشتین؟</h1>
<p style="margin:0 0 28px;font-size:15px;line-height:2;color:#42566d;">با زدن دکمه بازبینی رمز عبور، رمز جدید برای ورود به سامانه هوشمند مدیریت ساختمان وارد نمایید.</p>
<table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr><td align="center" bgcolor="#1f527f" style="border-radius:10px;mso-padding-alt:14px 26px;">
<a href="{{ $resetUrl }}" style="display:inline-block;padding:14px 26px;border:1px solid #1f527f;border-radius:10px;background-color:#1f527f;color:#ffffff;text-decoration:none;font-size:16px;line-height:1.6;font-weight:400;">بازبینی رمز عبور</a>
</td></tr></table>
<p style="clear:both;margin:28px 0 8px;font-size:13px;line-height:2;color:#52647a;">این لینک تا {{ $expires }} دقیقه اعتبار دارد و پس از تغییر رمز، قابل استفادهٔ مجدد نیست.</p>
<p style="margin:0;font-size:13px;line-height:2;color:#52647a;">اگر این درخواست را ثبت نکرده‌اید، نیازی به اقدامی نیست؛ رمز عبور شما تغییری نمی‌کند.</p>
<div style="margin-top:24px;padding-top:18px;border-top:1px solid #e3eaf1;">
<p style="font-size:12px;color:#52647a;line-height:1.9;margin:0 0 8px;">اگر دکمه باز نمی‌شود، نشانی زیر را در مرورگر کپی کنید:</p>
<a dir="ltr" href="{{ $resetUrl }}" style="display:block;direction:ltr;text-align:left;word-break:break-all;overflow-wrap:anywhere;color:#1f527f;font-size:12px;line-height:1.8;">{{ $resetUrl }}</a>
</div>
</td></tr>
<tr><td dir="rtl" align="center" style="padding:20px 24px;background-color:#f7f9fc;border-top:1px solid #e3eaf1;color:#52647a;font-size:13px;line-height:2;">
<p style="margin:0 0 8px;">بیلدینو | سامانه هوشمند مدیریت ساختمان</p>
@if ($supportEmail)
<div>ایمیل: <a href="mailto:{{ $supportEmail }}" dir="ltr" style="display:inline-block;color:#1f527f;text-decoration:none;">{{ $supportEmail }}</a></div>
@endif
@if ($supportPhone)
<div>تماس پشتیبانی: <span dir="ltr" style="display:inline-block;color:#172f49;">{{ $supportPhone }}</span></div>
@endif
</td></tr></table>
</td></tr></table>
</body></html>
