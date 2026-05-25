<div align="center">
  <h1>🚀 DownloadHub Bot</h1>
  <p><strong>ربات دانلود همه‌کاره برای پیام‌رسان بله</strong></p>
  <p>دانلود از یوتیوب، اینستاگرام، تیک‌تاک، ساندکلاد، فیسبوک، تلگرام، اینترنت آرشیو و ۱۸۰۰+ سایت دیگر — مستقیماً توی کانال شخصی خودت!</p>
</div>

<hr>

<h2>📥 از کجاها میتونم دانلود کنم؟</h2>

<table border="1" cellpadding="10" cellspacing="0" width="100%">
  <thead>
    <tr style="background-color:#f2f2f2;">
      <th>پلتفرم</th>
      <th>نوع محتوا</th>
      <th>فرمت‌های پشتیبانی‌شده</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>🎬 <strong>YouTube</strong></td>
      <td>ویدیو، پلی‌لیست، کانال</td>
      <td>MP4 (720p/480p), MP3</td>
    </tr>
    <tr>
      <td>📸 <strong>Instagram</strong></td>
      <td>پست، ریلز، استوری</td>
      <td>MP4 (720p/480p)</td>
    </tr>
    <tr>
      <td>🎵 <strong>TikTok</strong></td>
      <td>ویدیو (بدون واترمارک)</td>
      <td>MP4 (720p/480p)</td>
    </tr>
    <tr>
      <td>🎧 <strong>SoundCloud</strong></td>
      <td>موزیک، پادکست</td>
      <td>MP3 (کیفیت بالا/متوسط)</td>
    </tr>
    <tr>
      <td>📘 <strong>Facebook</strong></td>
      <td>ویدیو، ریلز، پست متنی</td>
      <td>MP4 (720p/480p)</td>
    </tr>
    <tr>
      <td>📨 <strong>Telegram</strong></td>
      <td>ویدیو، موزیک، اسناد، متن</td>
      <td>MP4, MP3, اسناد (ZIP, PDF, EXE)</td>
    </tr>
    <tr>
      <td>📚 <strong>Internet Archive</strong></td>
      <td>کتاب، اسناد تاریخی</td>
      <td>PDF, EPUB, TXT, DJVU</td>
    </tr>
    <tr>
      <td>🌐 <strong>Generic</strong></td>
      <td>۱۸۰۰+ سایت دیگه</td>
      <td>تشخیص خودکار توسط yt-dlp</td>
    </tr>
  </tbody>
</table>

<hr>

<h2>⚡ ویژگی‌های کلیدی</h2>

<ul>
  <li><strong>سیستم کش دو لایه:</strong> دانلود تکراری انجام نمیشه! فایل‌های دانلودشده بین همه کاربرا به اشتراک گذاشته میشه (⏱ زیر ۱ ثانیه)</li>
  <li><strong>آپلود مستقیم توی کانال:</strong> فایل‌ها مستقیم توی کانال شخصی شما آپلود میشن</li>
  <li><strong>مدیریت حجم فایل:</strong> کاربرای رایگان تا ۴۵MB دانلود میکنن، کاربرای ویژه بدون محدودیت</li>
  <li><strong>سیستم صف هوشمند:</strong> درخواست‌ها یکی یکی پردازش میشن — بدون فشار روی سرور</li>
  <li><strong>پشتیبانی:</strong> پیام مستقیم به مدیر ربات</li>
</ul>

<hr>

<h2>🛠️ چطور ربات رو روی هاست خودم راه‌اندازی کنم؟</h2>

<h3>۱. یه هاست سی‌پنل با PHP 8.2 بگیر</h3>

<p>یه هاست اشتراکی ساده (حتی رایگان) کافیه. فقط مطمئن شو <code>curl</code> و <code>SQLite</code> فعال باشه.</p>

<h3>۲. فایل ربات رو آپلود کن</h3>

<ol>
  <li>فایل <code>bot.php</code> رو در <strong>ریشه (public_html)</strong> یا یه پوشه دلخواه آپلود کن.</li>
  <li>فایل باید دقیقاً با اسم <code>bot.php</code> ذخیره بشه.</li>
</ol>

<h3>۳. تنظیمات ربات رو انجام بده</h3>

<p>اولین خطوط فایل <code>bot.php</code> رو باز کن و این مقادیر رو با اطلاعات خودت جایگزین کن:</p>

<pre style="background-color:#f5f5f5; padding:15px; border-radius:5px; overflow-x:auto;">
<code>define('BALE_BOT_TOKEN', '<span style="color:#e74c3c;">اینجا_توکن_رباتت_رو_بذار</span>');
define('GITHUB_TOKEN', '<span style="color:#e74c3c;">اینجا_Personal_Access_Token_گیت‌هاب_رو_بذار</span>');
define('GITHUB_REPO_OWNER', '<span style="color:#e74c3c;">یوزرنیم_گیت‌هاب_تو</span>');
define('GITHUB_REPO_NAME', '<span style="color:#e74c3c;">اسم_ریپوی_فورک‌شده</span>');
define('ADMIN_USER_ID', <span style="color:#e74c3c;">آیدی_عددی_خودت_در_بله</span>);
define('SPONSOR_CHANNEL', '<span style="color:#e74c3c;">@آیدی_کانال_اسپانسر</span>');
</code></pre>

<h3>۴. ست کردن Webhook</h3>

<p>این آدرس رو توی مرورگرت باز کن:</p>

<pre style="background-color:#f5f5f5; padding:15px; border-radius:5px; overflow-x:auto;">
<code>https://tapi.bale.ai/bot<TOKEN_RABAT>/setWebhook?url=https://yourdomain.com/bot.php</code></pre>

<p>به جای <code>TOKEN_RABAT</code> توکن رباتت رو بذار، و به جای <code>yourdomain.com/bot.php</code> آدرس فایلی که آپلود کردی.</p>

<p>باید این پیام رو ببینی: <code>{"ok":true,"result":true,"description":"Webhook was set"}</code></p>

<hr>

<h2>🔧 تنظیمات گیت‌هاب</h2>

<p>اکشن‌های دانلود توی گیت‌هاب اجرا میشن. باید یه سری کار انجام بدی:</p>

<ol>
  <li>این ریپو رو <strong>Fork</strong> کن (دکمه Fork بالای صفحه).</li>
  <li>تو ریپوی فورک‌شده، برو به <code>Settings → Secrets and variables → Actions</code>.</li>
  <li>یه <strong>Secret</strong> جدید به اسم <code>BALE_BOT_TOKEN</code> بساز و توکن رباتت رو بذار.</li>
  <li>مطمئن شو توکن <code>GITHUB_TOKEN</code> که توی فایل <code>bot.php</code> می‌ذاری، یه <strong>Personal Access Token (Classic)</strong> با دسترسی <code>workflow</code> باشه.</li>
</ol>

<hr>

<h2>📁 ساختار پروژه</h2>

<pre style="background-color:#f5f5f5; padding:15px; border-radius:5px; overflow-x:auto;">
<code>.
├── .github/workflows/
│   ├── archive-dl.yml       # Internet Archive
│   ├── facebook-dl.yml      # Facebook
│   ├── generic-dl.yml       # Generic (1800+ sites)
│   ├── instagram-dl.yml     # Instagram
│   ├── soundcloud-dl.yml    # SoundCloud
│   ├── telegram-dl.yml      # Telegram
│   ├── tiktok-dl.yml        # TikTok
│   └── yt-dl.yml            # YouTube
└── bot.php                  # سورس اصلی ربات
</code></pre>

<hr>

<div align="center">
  <p><strong>⭐ به پروژه ستاره بده تا بقیه هم استفاده کنن!</strong></p>
  <p>Made with ❤️ by <a href="https://github.com/khashayardev">Khashayar</a></p>
</div>
