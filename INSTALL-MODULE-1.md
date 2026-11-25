# LWE Learning Portal — Module 1 (Foundation): Install Guide

Ye zip **sirf hamari files** rakhta hai (`app/`, `routes/`, `database/`, `resources/`, `tests/` …).
Laravel aur packages aap ke PC par Composer/NPM se install honge, phir ye files unke upar copy hongi.

> Har command ke saath likha hai ke **kahan** chalani hai.
> 🖥️ = aap ka **Windows PC** (PowerShell, project folder ke andar)

---

## 0. Pehle ye cheezen PC par honi chahiye (ek dafa)

| Cheez | Version | Check command (🖥️ PowerShell) |
|---|---|---|
| PHP | 8.4+ (extensions: `pdo_sqlite`, `pdo_mysql`, `gd`, `zip`, `fileinfo`, `intl`, `mbstring`) | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 20+ | `node -v` |

Asaan tareeqa: **Laragon** install karein — PHP, Composer, Node aur MySQL sab saath aa jate hain.

---

## 1. Naya Laravel project banayein

🖥️ PowerShell (jis folder mein projects rakhte hain, jaise `C:\projects`):

```powershell
composer create-project laravel/laravel lwe-portal
cd lwe-portal
```

## 2. Breeze (Vue + Inertia) aur Spatie packages

🖥️ PowerShell (`lwe-portal` folder ke andar):

```powershell
composer require laravel/breeze --dev
php artisan breeze:install vue --pest --no-interaction
composer require spatie/laravel-permission spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```

## 3. Module 1 ki files copy karein (overwrite)

Zip ko `lwe-portal` folder ke andar unzip karein aur **har file replace** karein.

🖥️ PowerShell (zip ko Downloads mein maan kar):

```powershell
Expand-Archive -Path "$HOME\Downloads\lwe-module1.zip" -DestinationPath . -Force
```

## 4. `.env` settings

🖥️ `lwe-portal\.env` file Notepad/VS Code mein kholen aur **neeche ye lines add karein**:

```env
APP_NAME="LWE Learning Portal"
QUEUE_CONNECTION=sync
MAIL_MAILER=log

LWE_TIMEZONE=Asia/Karachi
WAFLOW_ENABLED=false
WAFLOW_BASE_URL=https://hub.learningwithearning.org
WAFLOW_API_TOKEN=
WAFLOW_SEND_PATH=/api/messages/send

ANTHROPIC_API_KEY=
AI_MODEL=claude-haiku-5-5
```

- Local testing ke liye database **SQLite** hi rehne dein (Laravel ka default) — MySQL ki zaroorat nahi.
- `WAFLOW_ENABLED=false` ka matlab: WhatsApp messages aur OTP codes asal mein nahi jaayenge, balke
  `storage\logs\laravel.log` mein likhe jaayenge. Testing ke dauran code wahin se parh lein.

## 5. Database, demo data aur frontend

🖥️ PowerShell:

```powershell
php artisan storage:link
php artisan migrate:fresh --seed
npm install
npm run build
```

## 6. Tests chalayen

🖥️ PowerShell:

```powershell
php artisan test
```

**Is command ka poora output mujhe bhej dein** — jo test fail hoga main theek kar doon ga.

## 7. Portal chalayen

🖥️ PowerShell:

```powershell
php artisan serve
```

(Agar Vue files badal rahe hon to ek doosri PowerShell window mein `npm run dev` bhi chalayen.
`composer run dev` Windows par `pail` ki wajah se error de sakta hai, is liye upar wala tareeqa rakhein.)

Phir browser 🌐 mein kholen: **http://localhost:8000**

### Demo logins (password sab ka: `password`)

| Role | Login |
|---|---|
| Super Admin | `admin@lwe.test` |
| VIP Mentor | `mentor@lwe.test` |
| Student | `03001234567` |

Super Admin login par 6-digit code maange ga (2-step). Code `storage\logs\laravel.log` ki aakhri lines mein milega.
Settings → "Admins must enter a code" ko off karke ye band kar sakte hain.

---

## Module 1 mein kya bana hai

- Login (phone ya email), student registration, **single-device login**, trusted-device lock, login history, many-device alert
- Forgot password: WhatsApp/email par **6-digit OTP**; admins ke liye **2-step login**
- Roles: super-admin, admin, mentor, student, franchiser (+ permissions)
- **Course wizard** (6 steps): basic → details → skills → fee (Free option) → completion rules → publish
- **Curriculum**: modules + lessons, drag-and-drop, 5 lesson types, drip (date / after previous), attachments (private)
- **Batches**: class days/time, seats, mentor
- **Enrollment**: student khud (free = foran, paid = payment), admin manual, **CSV import** (Google Sheet), duplicate check by phone
- **Payments**: screenshot + transaction ID upload (private), admin approve/reject (WhatsApp message), refund record
- **Coupons / scholarship codes**: %, fixed, expiry, max uses
- Student **Today** dashboard, course catalog, course page, lessons, progress
- **Notification bell**, **audit log**, settings, admin dashboard, students page (suspend, lock device, log out everywhere)
- **AI**: course builder, outline, lesson writer, payment screenshot checker (+ usage log aur daily limit)
- Pest tests: `tests/Feature/Lwe`

## Abhi kya baqi hai (agle modules)

- Module 2: Zoom classes portal ke andar, "Join" button 5 minute pehle, reminders, auto attendance
- Module 3: Tasks, quizzes, Q&A board, attendance
- Module 4: Protected video, automation (simulive), Skill Passport, mentor landing pages
- Module 5: Android/Windows app, earnings, reports, Urdu

## AI features

| Feature | Kahan | Kya karta hai |
|---|---|---|
| AI Course Builder | Courses → Edit → Details / Skills step | Notes se description, learning points, requirements, FAQs, skills |
| AI Outline | Course → Lessons → "Build outline with AI" | Poore course ke modules + lessons |
| AI Lesson Writer | Lesson dialog → "Write notes" | Lesson summary + notes (Roman Urdu / English / Urdu) |
| AI Payment Checker | Payments → Review | Screenshot parh kar amount/ID match, duplicate ID, editing ke nishan; reject note draft |

- AI ke liye `.env` mein `ANTHROPIC_API_KEY` daalein (Claude Console se API key banti hai). Key ke baghair portal chalta rahega, sirf AI buttons "AI key missing" batayenge.
- Settings → AI: on/off, rozana ki had (limit), aaj aur is mahine ka istemal.
- AI sirf draft banata hai; save/approve hamesha admin karta hai.

### AI not reaching ("cURL error 60" / SSL certificate)

Windows PHP ko HTTPS ke liye certificate file chahiye:
1. 🌐 Browser: https://curl.se/ca/cacert.pem download karein → `C:\php\extras\ssl\cacert.pem` (ya koi bhi folder) mein rakhein
2. 🖥️ `php --ini` chalayen → "Loaded Configuration File" wali `php.ini` Notepad mein kholen
3. Ye 2 lines dhoondein, aage ka `;` hata kar path likhein:
   `curl.cainfo = "C:\php\extras\ssl\cacert.pem"` aur `openssl.cafile = "C:\php\extras\ssl\cacert.pem"`
4. Usi file mein `;extension=gd` ka `;` hata dein (images/tests ke liye)
5. `php artisan serve` band karke dobara chalayen

## WaFlow Hub connect karna (jab tayyar hon)

`app/Services/WaFlowClient.php` mein `send()` function WaFlow Hub ke API ke mutabiq badalna hoga
(URL path aur JSON fields). Phir `.env` mein `WAFLOW_ENABLED=true` aur token daal dein.
