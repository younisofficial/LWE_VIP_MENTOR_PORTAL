# Module 2 — Live Classes (Zoom inside the portal)

## Kya bana hai

| Feature | Kahan |
|---|---|
| Zoom accounts jorna (encrypted keys, "Test connection") | Admin → **Zoom Accounts** (sirf Super Admin) |
| Classes schedule karna: batch, din, waqt, kitne hafte, lesson se lesson | Admin → **Live Classes** → *Schedule classes* |
| Zoom meeting khud banti hai; free Zoom account khud chuna jata hai | — |
| Double booking rok: ek Zoom account / ek mentor ek waqt mein ek hi class | — |
| Class **move** (reschedule) / **cancel** → students ko WhatsApp | Live Classes list |
| Student **Join** button 5 minute pehle khulta hai, class portal ke andar | Today page, **Live Classes** page |
| Moving name watermark + single-device check (doosri jagah login → class se bahar) | Class room |
| Mentor **Start class** (host) portal ke andar | Mentor dashboard, admin class page |
| **Auto attendance** (Zoom join/leave se; rejoins jud jate hain): Present / Partial / Absent | Class page |
| Haath se attendance theek karna, **CSV download** | Class page |
| Reminders: 1 din pehle, 1 ghanta pehle, Join khulne par, class miss karne par | Khud (scheduler) |
| **AI Class Notes**: recording transcript se summary, key points, homework, recap → mentor publish kare | Class page |
| Settings: join kitne minute pehle, attendance %, reminders on/off | Settings |

---

## Install (🖥️ Windows PC, PowerShell, `main-lwe-portal` folder)

```powershell
tar -xf "$HOME\Downloads\lwe-module2.zip" -C .
npm install @zoom/meetingsdk
npm run build
php artisan migrate
php artisan config:clear
php artisan test
```

Portal chalane ke liye **3 windows**:

```powershell
php artisan serve          # window 1: website
php artisan queue:work     # window 2: sirf agar .env mein QUEUE_CONNECTION=database ho (sync ho to zaroorat nahi)
php artisan schedule:work  # window 3: reminders har minute
```

---

## Zoom setup (🌐 browser: marketplace.zoom.us, Zoom admin account se)

Zoom ka **paid (licensed) user** chahiye jo host banega — free user ki meeting 40 minute mein band hoti hai.

### 1) Server-to-Server OAuth app (meetings banane ke liye)
1. Develop → Build App → **Server-to-Server OAuth App** → naam: *LWE Portal*
2. **App Credentials** se copy karein: **Account ID, Client ID, Client Secret**
3. **Scopes** mein ye ijazatein add karein: meetings create / update / delete karna, users parhna, user ka ZAK token parhna, cloud recordings parhna
4. **Activate** karein

### 2) General app with Meeting SDK (portal ke andar join karne ke liye)
1. Build App → **General App** → Features mein **Meeting SDK** on karein
2. **Client ID / Client Secret** copy karein — portal mein yehi **SDK Key / SDK Secret** hain
3. Isi app mein **Event Subscriptions** on karein:
   - URL: portal ke Zoom Accounts page par likha **Webhook URL**
   - Events: *Meeting started, Meeting ended, Participant joined, Participant left, All recordings have completed*
   - **Secret Token** copy karein → portal mein **Webhook Secret Token**

### 3) Zoom settings (🌐 zoom.us → Settings → Recording)
- **Cloud recording** on
- **Create audio transcript** on (AI Class Notes isi se bante hain)

### 4) Portal mein jorna
Admin → **Zoom Accounts** → *Add Zoom account* → saari keys + host email daalein → **Save & test** → "Connected" aana chahiye.

### Webhooks aap ke PC par
Zoom ko public **https** address chahiye. Live server par ye khud chalega. PC par test karne ke liye ngrok jaisa tunnel use karein:
```powershell
ngrok http 8000
```
Phir ngrok ka `https://….ngrok-free.app/webhooks/zoom` Zoom app mein URL ke taur par daalein, aur `.env` mein `APP_URL` bhi wohi rakhein.

---

## Test karne ka tareeqa
1. Zoom Accounts → account add → **Connected**
2. Live Classes → **Schedule classes** → batch, aaj se 10 minute baad ka waqt, 1 hafta → "Zoom: Ready"
3. Mentor (`mentor@lwe.test`) → **Start class** → Zoom portal ke andar khule, upar **HOST**
4. Student (doosra browser) → Today → 5 minute pehle **Join Class** active → class portal ke andar
5. Student ko teesre browser mein login karein → pehle browser ki class 30 second mein band ho jaye
6. Class khatam karein → class page par attendance (webhooks lage hon to)
7. Thodi der baad Zoom recording + transcript aaye → **AI class notes** → edit → **Save & publish** → student ke Live Classes page par "Class notes"
8. Ek class **Move** aur ek **Cancel** karein → student ki bell + `laravel.log` mein WhatsApp message

## Seedhi baat (limits)
- Browser mein student ko class ka meeting number/password technically mil sakta hai (SDK ko chahiye). Is liye watermark + attendance + single-device lagaya hai; poori recording-protection Module 5 ki app mein hogi.
- Agar alag alag Zoom **organizations** ke accounts jorein, Zoom app review maang sakta hai. Behtar hai sab hosts ek hi Zoom account ke users hon.
