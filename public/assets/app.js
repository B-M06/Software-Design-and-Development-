document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!window.confirm(el.getAttribute('data-confirm') || 'Are you sure?')) e.preventDefault();
    });
  });
  document.querySelectorAll('input[type="datetime-local"]').forEach(el => {
    // Keep browser-native local datetime controls; server stores Asia/Dhaka wall time.
  });
});

// Browser-based one-time GPS capture: location is only read after the user clicks.
document.querySelectorAll('[data-geolocate]').forEach((button) => {
  button.addEventListener('click', () => {
    const form = button.closest('form');
    const lat = form?.querySelector('[name="gps_lat"]');
    const lng = form?.querySelector('[name="gps_lng"]');
    if (!navigator.geolocation) { alert('Geolocation is not supported by this browser.'); return; }
    button.disabled = true; button.textContent = 'Getting location…';
    navigator.geolocation.getCurrentPosition((position) => {
      if (lat) lat.value = position.coords.latitude.toFixed(7);
      if (lng) lng.value = position.coords.longitude.toFixed(7);
      button.disabled = false; button.textContent = 'GPS location captured';
    }, (error) => {
      button.disabled = false; button.textContent = 'Use my current GPS location';
      alert(error.message || 'Could not access location. Allow location permission and try again.');
    }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
  });
});


// Optional local Banglish typing helper. It runs only in ordinary text fields;
// passwords, email addresses, URLs, phone numbers, dates and numeric fields are untouched.
(() => {
  const KEY = 'foodrescue-banglish-enabled';
  const common = {
    ami:'আমি', amra:'আমরা', amar:'আমার', apni:'আপনি', tumi:'তুমি', tomar:'তোমার', tomra:'তোমরা',
    valo:'ভালো', bhalo:'ভালো', khub:'খুব', achi:'আছি', acho:'আছো', ache:'আছে', ase:'আছে', nai:'নাই', nei:'নেই',
    ki:'কি', keno:'কেন', kivabe:'কিভাবে', kibhabe:'কিভাবে', kothay:'কোথায়', kokhon:'কখন', ke:'কে', kar:'কার',
    amar:'আমার', apnar:'আপনার', dhonnobad:'ধন্যবাদ', assalamu:'আসসালামু', alaikum:'আলাইকুম', salam:'সালাম',
    bangla:'বাংলা', bangladesh:'বাংলাদেশ', dhaka:'ঢাকা', khabar:'খাবার', khabo:'খাবো', khay:'খায়', pani:'পানি',
    food:'ফুড', rescue:'রেসকিউ', donation:'ডোনেশন', donate:'ডোনেট', restaurant:'রেস্টুরেন্ট', ngo:'এনজিও',
    dorkar:'দরকার', lagbe:'লাগবে', chai:'চাই', hobe:'হবে', korbo:'করবো', korun:'করুন', koro:'করো',
    korte:'করতে', kore:'করে', korchi:'করছি', hocche:'হচ্ছে', hoy:'হয়', hoye:'হয়ে', diye:'দিয়ে', den:'দেন', dao:'দাও',
    naam:'নাম', nam:'নাম', thikana:'ঠিকানা', elaka:'এলাকা', jela:'জেলা', phone:'ফোন', email:'ইমেইল',
    dhonnobad:'ধন্যবাদ', shob:'সব', sob:'সব', user:'ইউজার', notun:'নতুন', account:'অ্যাকাউন্ট', login:'লগইন', register:'রেজিস্টার',
    aj:'আজ', kal:'কাল', agami:'আগামী', ekhon:'এখন', pore:'পরে', sokal:'সকাল', dupur:'দুপুর', rat:'রাত',
    manush:'মানুষ', jonno:'জন্য', theke:'থেকে', sathe:'সাথে', ebong:'এবং', kintu:'কিন্তু', jodi:'যদি', tahole:'তাহলে',
    bari:'বাড়ি', poribar:'পরিবার', bondhu:'বন্ধু', kaj:'কাজ', project:'প্রজেক্ট', website:'ওয়েবসাইট',
    help:'হেল্প', sahajjo:'সাহায্য', proyojon:'প্রয়োজন', shundor:'সুন্দর', sundor:'সুন্দর', bhalo:'ভালো', valo:'ভালো',
    ha:'হ্যাঁ', hya:'হ্যাঁ', na:'না', thik:'ঠিক', thikache:'ঠিক আছে', jani:'জানি', janina:'জানি না',
    nam:'নাম', bari:'বাড়ি'
  };
  // Phonetic fallback for words not in the common-word dictionary.
  const rules = [
    [/ksh/g,'ক্ষ'],[/kkh/g,'ক্ষ'],[/ng/g,'ং'],[/chh/g,'ছ'],[/shch/g,'শ্চ'],[/kh/g,'খ'],[/gh/g,'ঘ'],[/ch/g,'চ'],[/jh/g,'ঝ'],[/th/g,'থ'],[/dh/g,'ধ'],[/ph/g,'ফ'],[/bh/g,'ভ'],[/sh/g,'শ'],[/ss/g,'স'],[/rr/g,'ড়'],[/ng/g,'ঙ'],[/ny/g,'ন্য'],[/oi/g,'ৈ'],[/ou/g,'ৌ'],[/ow/g,'ও'],[/aa/g,'া'],[/ee/g,'ী'],[/ii/g,'ী'],[/oo/g,'ূ'],[/uu/g,'ু'],[/ri/g,'ৃ'],[/a/g,'া'],[/i/g,'ি'],[/e/g,'ে'],[/u/g,'ু'],[/o/g,'ো'],[/k/g,'ক'],[/g/g,'গ'],[/c/g,'চ'],[/j/g,'জ'],[/t/g,'ত'],[/d/g,'দ'],[/n/g,'ন'],[/p/g,'প'],[/f/g,'ফ'],[/b/g,'ব'],[/m/g,'ম'],[/r/g,'র'],[/l/g,'ল'],[/s/g,'স'],[/h/g,'হ'],[/y/g,'য়'],[/w/g,'ও'],[/v/g,'ভ'],[/x/g,'ক্স'],[/z/g,'জ']
  ];
  function transliterate(word) {
    const lower = word.toLowerCase();
    if (common[lower]) return common[lower];
    let out = lower;
    for (const [pattern, replacement] of rules) out = out.replace(pattern, replacement);
    return out;
  }
  function enabled() { try { return localStorage.getItem(KEY) !== 'false'; } catch (_) { return true; } }
  function saveEnabled(value) { try { localStorage.setItem(KEY, value ? 'true' : 'false'); } catch (_) { /* Storage can be disabled; current-page toggle still works. */ } }
  function refreshToggle() {
    const btn = document.getElementById('banglishToggle');
    if (!btn) return;
    btn.textContent = enabled() ? 'বাংলা টাইপিং: চালু' : 'বাংলা টাইপিং: বন্ধ';
    btn.setAttribute('aria-pressed', String(enabled()));
    btn.title = enabled() ? 'Banglish লিখে Space চাপলে বাংলা হবে; English রাখতে toggle বন্ধ করুন' : 'Banglish-to-Bangla typing চালু করুন';
  }
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('banglishToggle');
    if (btn) btn.addEventListener('click', () => { saveEnabled(!enabled()); btn.dataset.pageEnabled = String(!enabled()); refreshToggle(); });
    refreshToggle();
    const eligible = el => {
      if (!(el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement)) return false;
      if (el instanceof HTMLTextAreaElement) return !el.disabled && !el.readOnly && !el.hasAttribute('data-no-banglish');
      const type = (el.type || 'text').toLowerCase();
      return ['text','search'].includes(type) && !el.disabled && !el.readOnly && !el.hasAttribute('data-no-banglish') && !/email|password|username|user_name|phone|mobile|url|slug|code|token|otp|district_code/i.test((el.name || '') + ' ' + (el.autocomplete || ''));
    };
    document.addEventListener('keydown', event => {
      const el = event.target;
      if (!enabled() || !eligible(el) || event.key !== ' ' || event.ctrlKey || event.metaKey || event.altKey) return;
      const start = el.selectionStart, end = el.selectionEnd;
      if (start == null || start !== end || start === 0) return;
      const before = el.value.slice(0, start);
      const match = before.match(/([A-Za-z]+)$/);
      if (!match) return;
      const word = match[1];
      const converted = transliterate(word);
      if (converted === word) return;
      event.preventDefault();
      const from = start - word.length;
      el.setRangeText(converted + ' ', from, end, 'end');
      el.dispatchEvent(new Event('input', {bubbles:true}));
    });
  });
})();

// Theme toggle for the redesigned homepage and shared navigation.
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('themeToggle');
  if (!toggle) return;
  let dark = false;
  try { dark = localStorage.getItem('foodrescue-theme') === 'dark'; } catch (_) {}
  const applyTheme = () => { document.body.classList.toggle('dark-mode', dark); toggle.textContent = dark ? '☾' : '☼'; toggle.setAttribute('aria-pressed', String(dark)); };
  toggle.addEventListener('click', () => { dark = !dark; try { localStorage.setItem('foodrescue-theme', dark ? 'dark' : 'light'); } catch (_) {} applyTheme(); });
  applyTheme();
});


// Keep the signed-in user's unread notification badge fresh without a page reload.
document.addEventListener('DOMContentLoaded', () => {
  const badge = document.getElementById('notificationCount');
  const bell = document.getElementById('notificationBell');
  if (!badge || !bell) return;
  const endpoint = bell.href.replace(/notifications\.php(?:\?.*)?$/, 'api/notification_count.php');
  const refreshNotificationCount = async () => {
    try {
      const response = await fetch(endpoint, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      if (!response.ok) return;
      const data = await response.json();
      const count = Math.max(0, Number(data.unread) || 0);
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.hidden = count === 0;
      bell.setAttribute('aria-label', `Notifications, ${count} unread`);
    } catch (_) { /* Keep the last known count if the network is temporarily unavailable. */ }
  };
  refreshNotificationCount();
  window.setInterval(refreshNotificationCount, 30000);
});


// Site-wide English/Bangla language switch. Applies on every page using the shared layout.
(() => {
  const STORAGE_KEY = 'foodrescue-language';
  const translations = {
    'Home':'হোম','About':'আমাদের সম্পর্কে','Events':'ইভেন্ট','Reports':'রিপোর্ট','NGO':'এনজিও','Volunteer':'স্বেচ্ছাসেবক','Food Categories':'খাবারের ধরন','Browse by type':'ধরন অনুযায়ী দেখুন','Food Categories':'খাবারের ধরন','Available Food':'উপলব্ধ খাবার','Nearby NGOs':'কাছাকাছি এনজিও','Impact':'প্রভাব','My Dashboard':'আমার ড্যাশবোর্ড',
    'Log in':'লগইন','Log out':'লগআউট','Join FoodRescue':'FoodRescue-এ যোগ দিন','Create account':'অ্যাকাউন্ট তৈরি করুন','Dashboard':'ড্যাশবোর্ড','Profile':'প্রোফাইল','Notifications':'নোটিফিকেশন','Admin panel':'অ্যাডমিন প্যানেল','Manage users':'ব্যবহারকারী পরিচালনা','Hello, User':'হ্যালো, ব্যবহারকারী','Language':'ভাষা',
    'Save Food · Feed People · Build a Better Tomorrow':'খাবার বাঁচাই · মানুষকে খাওয়াই · সুন্দর আগামী গড়ি','Rescue surplus food. Connect communities. Reduce waste.':'উদ্বৃত্ত খাবার উদ্ধার করুন। কমিউনিটিকে যুক্ত করুন। অপচয় কমান।','Designed for Bangladesh · Currency: BDT (৳) · Time zone: Asia/Dhaka':'বাংলাদেশের জন্য তৈরি · মুদ্রা: টাকা (৳) · সময় অঞ্চল: এশিয়া/ঢাকা',
    'ADMINISTRATION':'প্রশাসন','Audit':'নিরীক্ষা','log.':'লগ।','All entities':'সব তথ্য','Filter':'ফিল্টার','When':'সময়','Who':'কে','Action':'কাজ','Entity':'সত্তা','Details':'বিস্তারিত','No log entries found.':'কোনো লগ পাওয়া যায়নি।','← Previous':'← আগের','Next →':'পরের →','Next':'পরবর্তী','Review':'পর্যালোচনা','Save':'সংরক্ষণ','Cancel':'বাতিল','Back':'ফিরে যান','Edit':'সম্পাদনা','Delete':'মুছুন','View':'দেখুন','Status':'অবস্থা','Date':'তারিখ','Time':'সময়','Name':'নাম','Email address':'ইমেইল ঠিকানা','Password':'পাসওয়ার্ড','Address':'ঠিকানা','Phone':'ফোন','Contact':'যোগাযোগ','Role':'ভূমিকা','Actions':'কার্যক্রম','Quantity':'পরিমাণ','Food':'খাবার','Category':'ধরন','Description':'বিবরণ','Notes':'নোট','Submit':'জমা দিন','Submit complaint':'অভিযোগ জমা দিন','Update':'আপডেট','Update report':'রিপোর্ট আপডেট','Apply filters':'ফিল্টার প্রয়োগ','Reset':'রিসেট','Search':'খুঁজুন','Search food...':'খাবার খুঁজুন...','Choose area':'এলাকা বেছে নিন','Dhaka':'ঢাকা','Mirpur':'মিরপুর','Uttara':'উত্তরা','Dhanmondi':'ধানমন্ডি','Gulshan':'গুলশান',
    'WORKSPACE':'কর্মক্ষেত্র','Welcome back,':'আবার স্বাগতম,','Your FoodRescue workspace, with the tools you need in one place.':'আপনার প্রয়োজনীয় সব টুল এক জায়গায়—আপনার FoodRescue কর্মক্ষেত্র।','Edit profile ↗':'প্রোফাইল সম্পাদনা ↗','Verification pending.':'যাচাইকরণ অপেক্ষমাণ।','Some actions may remain restricted until an administrator verifies your account.':'অ্যাডমিন আপনার অ্যাকাউন্ট যাচাই না করা পর্যন্ত কিছু কাজ সীমিত থাকতে পারে।','Food listings':'খাবারের তালিকা','View listings →':'তালিকা দেখুন →','Open requests →':'খোলা অনুরোধ →','Demand reports':'চাহিদার রিপোর্ট','View demand →':'চাহিদা দেখুন →','Track deliveries →':'ডেলিভারি ট্র্যাক করুন →','Platform users':'প্ল্যাটফর্মের ব্যবহারকারী','Manage users →':'ব্যবহারকারী পরিচালনা →','YOUR WORKSPACE':'আপনার কর্মক্ষেত্র','All tools, one dashboard':'সব টুল, একটি ড্যাশবোর্ড','tools':'টি টুল',
    'Create account ↗':'অ্যাকাউন্ট তৈরি করুন ↗','Already registered?':'আগেই নিবন্ধিত?','All fields are required unless marked optional.':'ঐচ্ছিক হিসেবে চিহ্নিত নয় এমন সব ঘর পূরণ করতে হবে।','Full name / organization contact':'পূর্ণ নাম / প্রতিষ্ঠানের যোগাযোগ','Bangladesh mobile':'বাংলাদেশি মোবাইল নম্বর','Account type':'অ্যাকাউন্টের ধরন','Choose your role':'আপনার ভূমিকা বেছে নিন','Restaurant / Food donor':'রেস্টুরেন্ট / খাবার দাতা','Event organizer (wedding, conference, party, community event)':'ইভেন্ট আয়োজক (বিয়ে, সম্মেলন, পার্টি, কমিউনিটি ইভেন্ট)','NGO / Receiving organization':'এনজিও / গ্রহণকারী সংস্থা','Demand reporter (shelter / orphanage / field worker)':'চাহিদা রিপোর্টকারী (আশ্রয়কেন্দ্র / এতিমখানা / মাঠকর্মী)','Volunteer / Delivery partner':'স্বেচ্ছাসেবক / ডেলিভারি সহযোগী','Area and address':'এলাকা ও ঠিকানা','Create an account':'অ্যাকাউন্ট তৈরি করুন','WELCOME BACK':'আবার স্বাগতম','Good to see':'আপনাকে দেখে ভালো লাগছে','you again.':'আবারও।','Every rescued meal starts with people who care.':'প্রতিটি উদ্ধার করা খাবারের পেছনে রয়েছে যত্নশীল মানুষ।','Food is too valuable to waste, and community is too powerful to ignore.':'খাবার অপচয় করার মতো তুচ্ছ নয়, আর কমিউনিটির শক্তি উপেক্ষা করার মতো নয়।','YOUR ACCOUNT':'আপনার অ্যাকাউন্ট','Access your FoodRescue workspace.':'আপনার FoodRescue কর্মক্ষেত্রে প্রবেশ করুন।','Log in securely ↗':'নিরাপদে লগইন করুন ↗','New to FoodRescue?':'FoodRescue-এ নতুন?','Local demo accounts (development only)':'স্থানীয় ডেমো অ্যাকাউন্ট (শুধু ডেভেলপমেন্টের জন্য)','Shared demo password:':'সবার ডেমো পাসওয়ার্ড:','Use':'ব্যবহার করুন','click here to set up demo accounts and food data':'ডেমো অ্যাকাউন্ট ও খাবারের তথ্য সেটআপ করতে এখানে ক্লিক করুন',
    'Available food':'উপলব্ধ খাবার','Browse current listings. Food is shown only while its safety deadline has not passed.':'বর্তমান তালিকা দেখুন। নিরাপদ ব্যবহারের সময়সীমা শেষ না হওয়া পর্যন্ত খাবার দেখানো হবে।','View area-wise map ↗':'এলাকাভিত্তিক মানচিত্র দেখুন ↗','Smart matches ↗':'স্মার্ট মিল দেখুন ↗','No active food listings found':'কোনো সক্রিয় খাবারের তালিকা পাওয়া যায়নি','Local demo data not showing?':'স্থানীয় ডেমো তথ্য দেখা যাচ্ছে না?','Set up demo data':'ডেমো তথ্য সেটআপ করুন','Priority':'অগ্রাধিকার','Safe pickup deadline':'নিরাপদ সংগ্রহের শেষ সময়','Countdown loading…':'সময় গণনা হচ্ছে…','Donated by':'দান করেছেন','Event donation':'ইভেন্টের খাবার দান','Request food ↗':'খাবারের অনুরোধ ↗','Log in to request':'অনুরোধ করতে লগইন করুন','Request Food →':'খাবারের অনুরোধ →','Quantity:':'পরিমাণ:','Expiry:':'মেয়াদ:','DEMO':'ডেমো','Demo cards are sample listings for preview only. Real donations and requests are managed through the database.':'ডেমো কার্ডগুলো শুধু নমুনা। প্রকৃত দান ও অনুরোধ ডেটাবেসের মাধ্যমে পরিচালিত হয়।',
    'Post surplus':'উদ্বৃত্ত খাবার দিন','food.':'খাবার।','Food name':'খাবারের নাম','Select category':'ধরন নির্বাচন করুন','Food photo (optional, JPG/PNG/WEBP, max 3 MB)':'খাবারের ছবি (ঐচ্ছিক, JPG/PNG/WEBP, সর্বোচ্চ ৩ MB)','Quantity (kg)':'পরিমাণ (কেজি)','Event name':'ইভেন্টের নাম','Event type':'ইভেন্টের ধরন','Select event type':'ইভেন্টের ধরন নির্বাচন করুন','Event date & time':'ইভেন্টের তারিখ ও সময়','Cooked / prepared time':'রান্না / প্রস্তুতের সময়','Safe pickup deadline':'নিরাপদ সংগ্রহের শেষ সময়','Storage condition':'সংরক্ষণের অবস্থা','Storage method':'সংরক্ষণ পদ্ধতি','Unknown / needs review':'অজানা / পর্যালোচনা প্রয়োজন','Hot-held':'গরম অবস্থায় রাখা','Refrigerated':'রেফ্রিজারেটরে রাখা','Room temperature':'কক্ষের তাপমাত্রা','Frozen':'হিমায়িত','Measured food temperature (°C)':'মাপা খাবারের তাপমাত্রা (°C)','Ambient temperature (°C)':'পরিবেশের তাপমাত্রা (°C)','Room-temperature exposure (minutes)':'কক্ষের তাপমাত্রায় থাকার সময় (মিনিট)','Allergens (if known)':'অ্যালার্জির উপাদান (জানা থাকলে)','Pickup address in Bangladesh':'বাংলাদেশে সংগ্রহের ঠিকানা','Publish food listing ↗':'খাবারের তালিকা প্রকাশ ↗',
    'My food':'আমার খাবার','listings.':'তালিকা।','Track safety review and status of everything you have posted.':'আপনার পোস্ট করা সব খাবারের নিরাপত্তা যাচাই ও অবস্থা দেখুন।','+ Post surplus food':'+ উদ্বৃত্ত খাবার পোস্ট করুন','Qty':'পরিমাণ','Safety review':'নিরাপত্তা যাচাই','Batch QR / Track':'ব্যাচ QR / ট্র্যাক','You have not posted any food yet.':'আপনি এখনো কোনো খাবার পোস্ট করেননি।','Donation':'দান','requests.':'অনুরোধ।','Follow requests from submission to approval and delivery.':'জমা দেওয়া থেকে অনুমোদন ও ডেলিভারি পর্যন্ত অনুরোধ অনুসরণ করুন।','Partner':'সহযোগী','Deadline':'শেষ সময়','Accept':'গ্রহণ করুন','Reject':'প্রত্যাখ্যান','No donation requests yet.':'এখনো কোনো দানের অনুরোধ নেই।',
    'Your':'আপনার','notifications.':'নোটিফিকেশন।','Important account and rescue-workflow updates appear here.':'অ্যাকাউন্ট ও খাবার উদ্ধারের গুরুত্বপূর্ণ আপডেট এখানে দেখানো হবে।','Mark all as read':'সব পড়া হয়েছে হিসেবে চিহ্নিত করুন','Mark read':'পড়া হয়েছে হিসেবে চিহ্নিত করুন',"You're all caught up":'সব আপডেট দেখা হয়েছে','No notifications yet.':'এখনো কোনো নোটিফিকেশন নেই।','No notifications yet':'এখনো কোনো নোটিফিকেশন নেই',
    'My':'আমার','profile.':'প্রোফাইল।','Update your contact details or change your password.':'যোগাযোগের তথ্য আপডেট করুন অথবা পাসওয়ার্ড পরিবর্তন করুন।','(email cannot be changed)':'(ইমেইল পরিবর্তন করা যাবে না)','Save profile':'প্রোফাইল সংরক্ষণ','Change password':'পাসওয়ার্ড পরিবর্তন','Current password':'বর্তমান পাসওয়ার্ড','New password (min 8 characters)':'নতুন পাসওয়ার্ড (কমপক্ষে ৮ অক্ষর)','Confirm new password':'নতুন পাসওয়ার্ড নিশ্চিত করুন',
    'Nearby':'কাছাকাছি','NGO finder.':'এনজিও খোঁজ।','Organisation':'সংস্থা','Type':'ধরন','Map':'মানচিত্র','Open map ↗':'মানচিত্র খুলুন ↗','No verified organisations with coordinates yet.':'এখনো অবস্থানসহ যাচাইকৃত কোনো সংস্থা নেই।','Food demand':'খাবারের চাহিদা','by area.':'এলাকাভিত্তিক।','Area / district':'এলাকা / জেলা','People':'মানুষ','Meals required':'প্রয়োজনীয় খাবারের পরিমাণ','Fulfilled':'পূরণ হয়েছে','Needed by':'প্রয়োজনের সময়','Urgency':'জরুরিতা','No demand requests available yet.':'এখনো কোনো চাহিদার অনুরোধ নেই।','Area demand gap':'এলাকাভিত্তিক চাহিদার ঘাটতি','View demand map ↗':'চাহিদার মানচিত্র দেখুন ↗',
    'Report food':'খাবারের চাহিদা রিপোর্ট','demand.':'চাহিদা।','Specific area / community name':'নির্দিষ্ট এলাকা / কমিউনিটির নাম','District':'জেলা','Upazila / Thana':'উপজেলা / থানা','Ward (optional)':'ওয়ার্ড (ঐচ্ছিক)','People in need':'সহায়তাপ্রার্থী মানুষ','Normal':'স্বাভাবিক','Low':'কম','High':'উচ্চ','Critical':'অতি জরুরি','Notes / dietary needs':'নোট / খাদ্যসংক্রান্ত প্রয়োজন','Submit demand for verification ↗':'যাচাইয়ের জন্য চাহিদা জমা দিন ↗','My demand':'আমার চাহিদা','reports.':'রিপোর্ট।','+ Report new demand':'+ নতুন চাহিদা রিপোর্ট','Meals (fulfilled / required)':'খাবার (পূরণ / প্রয়োজন)','You have not reported any demand yet.':'আপনি এখনো কোনো চাহিদা রিপোর্ট করেননি।',
    'Impact reports.':'প্রভাবের রিপোর্ট।','Food rescued / reserved':'উদ্ধার / সংরক্ষিত খাবার','Donation requests':'খাবারের অনুরোধ','Meals distributed':'বিতরণ করা খাবার','Areas reporting need':'চাহিদা রিপোর্ট করা এলাকা','Download CSV report ↓':'CSV রিপোর্ট ডাউনলোড ↓','Open Report Builder ↗':'রিপোর্ট তৈরি করার পেজ খুলুন ↗','Environmental Impact ↗':'পরিবেশগত প্রভাব ↗','Transparency note':'স্বচ্ছতা সংক্রান্ত নোট','Food waste':'খাবারের অপচয়','analytics.':'বিশ্লেষণ।','From date':'শুরুর তারিখ','To date':'শেষ তারিখ','Apply filters':'ফিল্টার প্রয়োগ','Reset':'রিসেট','Listings':'তালিকা','Listed quantity (kg)*':'তালিকাভুক্ত পরিমাণ (কেজি)*','Delivered quantity (kg)**':'ডেলিভারি করা পরিমাণ (কেজি)**','Expired listing quantity (kg)':'মেয়াদোত্তীর্ণ তালিকার পরিমাণ (কেজি)','How to read these metrics':'এই পরিমাপগুলো কীভাবে বুঝবেন','CATEGORY BREAKDOWN':'ধরনভিত্তিক বিভাজন','Category breakdown':'ধরনভিত্তিক বিভাজন','Category':'ধরন','No food records for this date range.':'এই সময়সীমায় খাবারের কোনো রেকর্ড নেই।',
    'Volunteer':'স্বেচ্ছাসেবক','Select verified volunteer':'যাচাইকৃত স্বেচ্ছাসেবক নির্বাচন করুন','Start latitude':'শুরুর অক্ষাংশ','Start longitude':'শুরুর দ্রাঘিমাংশ','Select 2–15 deliveries':'২–১৫টি ডেলিভারি নির্বাচন করুন','Optimize stop order':'স্টপের ক্রম অপ্টিমাইজ করুন','Suggested route':'প্রস্তাবিত রুট','Order':'ক্রম','Delivery':'ডেলিভারি','Recipient':'গ্রহীতা','Coordinates':'স্থানাঙ্ক','Route status':'রুটের অবস্থা','Accepted':'গৃহীত','In progress':'চলমান','Completed':'সম্পন্ন','Cancelled':'বাতিল','Update route':'রুট আপডেট','Recent route plans':'সাম্প্রতিক রুট পরিকল্পনা','Distance estimate':'দূরত্বের আনুমানিক হিসাব','Open route':'রুট খুলুন','No route plans yet.':'এখনো কোনো রুট পরিকল্পনা নেই।','Use my current GPS location':'আমার বর্তমান GPS অবস্থান ব্যবহার করুন','GPS location captured':'GPS অবস্থান নেওয়া হয়েছে',
    'Create relief campaign':'ত্রাণ কার্যক্রম তৈরি করুন','Campaign title':'কার্যক্রমের শিরোনাম','Emergency type':'জরুরি অবস্থার ধরন','Flood':'বন্যা','Cyclone':'ঘূর্ণিঝড়','Community food drive':'কমিউনিটির খাবার বিতরণ','Other':'অন্যান্য','Target quantity':'লক্ষ্য পরিমাণ','Unit':'একক','Start':'শুরু','End':'শেষ','Create campaign':'কার্যক্রম তৈরি করুন','CAMPAIGN DIRECTORY':'কার্যক্রমের তালিকা','Active & recent campaigns':'সক্রিয় ও সাম্প্রতিক কার্যক্রম','Join campaign':'কার্যক্রমে যোগ দিন','Update status':'অবস্থা আপডেট','Paused':'বিরতিতে','No campaigns yet':'এখনো কোনো কার্যক্রম নেই',
    'Rating':'রেটিং','Select stars':'স্টার নির্বাচন করুন','Comment (optional)':'মন্তব্য (ঐচ্ছিক)','Submit feedback':'মতামত জমা দিন','COMMUNITY RATINGS':'কমিউনিটির রেটিং','Published ratings':'প্রকাশিত রেটিং','Average rating':'গড় রেটিং','Reviews':'রিভিউ','No published feedback yet.':'এখনো কোনো মতামত প্রকাশিত হয়নি।','YOUR REVIEWS':'আপনার রিভিউ','Feedback submitted':'জমা দেওয়া মতামত','No feedback submitted from this account.':'এই অ্যাকাউন্ট থেকে কোনো মতামত জমা দেওয়া হয়নি।',
    'Fraud & security':'জালিয়াতি ও নিরাপত্তা','review.':'পর্যালোচনা।','User / food':'ব্যবহারকারী / খাবার','Severity':'গুরুত্ব','Mark reviewing':'পর্যালোচনা চলছে চিহ্নিত করুন','Clear flag':'ফ্ল্যাগ সরান','Action taken':'গৃহীত পদক্ষেপ','Save review':'পর্যালোচনা সংরক্ষণ','Reviewed':'পর্যালোচিত','No risk flags recorded. Continue reviewing audit logs and user reports.':'কোনো ঝুঁকির চিহ্ন নেই। অডিট লগ ও ব্যবহারকারীর রিপোর্ট পর্যালোচনা চালিয়ে যান।',
    'Food safety':'খাদ্য নিরাপত্তা','Pending / flagged checks':'অপেক্ষমাণ / চিহ্নিত যাচাই','Restaurant':'রেস্টুরেন্ট','Current result':'বর্তমান ফলাফল','Mark safe':'নিরাপদ চিহ্নিত করুন','Mark unsafe':'অনিরাপদ চিহ্নিত করুন','Flag for follow-up':'পরবর্তী পর্যালোচনার জন্য চিহ্নিত করুন','No pending safety checks.':'কোনো নিরাপত্তা যাচাই অপেক্ষমাণ নেই।','DEMAND VALIDATION':'চাহিদা যাচাই','Pending demand requests':'অপেক্ষমাণ চাহিদার অনুরোধ','Validate':'যাচাই করুন','No pending demand requests.':'কোনো চাহিদার অনুরোধ অপেক্ষমাণ নেই।',
    'Smart donation':'স্মার্ট দান','matching.':'ম্যাচিং।','Safety and human review':'নিরাপত্তা ও মানবিক পর্যালোচনা','Food listing':'খাবারের তালিকা','Verified demand':'যাচাইকৃত চাহিদা','Score':'স্কোর','Why recommended':'সুপারিশের কারণ','No eligible food-demand pairs found. Check safe listings and validated demand reports.':'উপযুক্ত খাবার-চাহিদার মিল পাওয়া যায়নি। নিরাপদ তালিকা ও যাচাইকৃত চাহিদার রিপোর্ট দেখুন।','Find matches':'মিল খুঁজুন','All categories':'সব ধরন','% match':'% মিল','No approved food matches':'অনুমোদিত খাবারের কোনো মিল নেই','Try another area or check later.':'অন্য এলাকা চেষ্টা করুন অথবা পরে দেখুন।',
    'Create a broadcast':'জরুরি ঘোষণা তৈরি করুন','Title':'শিরোনাম','Target food (kg)':'লক্ষ্য খাবার (কেজি)','Expires in hours':'মেয়াদ শেষ হবে (ঘণ্টায়)','Create emergency broadcast':'জরুরি ঘোষণা তৈরি করুন','ACTIVE BROADCASTS':'সক্রিয় ঘোষণা','Open requests':'খোলা অনুরোধ','Available to help':'সহায়তা করতে পারব','Can take this request':'এই অনুরোধটি নিতে পারব','Unable to help':'সহায়তা করতে পারব না','Food available (kg)':'উপলব্ধ খাবার (কেজি)','Message':'বার্তা','Submit response':'উত্তর জমা দিন','Close broadcast':'ঘোষণা বন্ধ করুন','No active emergency broadcasts':'কোনো সক্রিয় জরুরি ঘোষণা নেই',
    'Create route proposal':'রুটের প্রস্তাব তৈরি করুন','Select 2–15 deliveries':'২–১৫টি ডেলিভারি নির্বাচন করুন','Route planner.':'রুট পরিকল্পনা।','Donation certificate.':'দানের সনদ।','Certificates are available for delivered or completed donations associated with your account.':'আপনার অ্যাকাউন্টের সঙ্গে যুক্ত ডেলিভারি করা বা সম্পন্ন দানের সনদ পাওয়া যাবে।','Certificate not available':'সনদ পাওয়া যায়নি','View donation requests':'দানের অনুরোধ দেখুন','Certificate of Appreciation':'প্রশংসাপত্র','Print / Save as PDF ↗':'প্রিন্ট / PDF হিসেবে সংরক্ষণ ↗','Back to impact reports':'প্রভাবের রিপোর্টে ফিরে যান',
    'Partner API':'পার্টনার API','portal.':'পোর্টাল।','Copy this API key now':'এখনই এই API key কপি করুন','Copy API key':'API key কপি করুন','Create API key':'API key তৈরি করুন','Key label':'Key-এর নাম','Permission scope':'অনুমতির পরিধি','Read impact summary':'প্রভাবের সারাংশ পড়ুন','Read completed donations':'সম্পন্ন দানের তথ্য পড়ুন','Your API keys':'আপনার API key','Prefix':'প্রিফিক্স','Scope':'পরিধি','Expires':'মেয়াদ শেষ','Last used':'সর্বশেষ ব্যবহার','Revoke':'বাতিল করুন','No API keys created.':'কোনো API key তৈরি করা হয়নি।','Available endpoints':'উপলব্ধ endpoint','No verified organisations with coordinates yet.':'এখনো স্থানাঙ্কসহ যাচাইকৃত সংস্থা নেই।',
    'Area-wise food':'এলাকাভিত্তিক খাবার','map.':'মানচিত্র।','Filter area':'এলাকা ফিল্টার','All areas':'সব এলাকা','No food markers yet.':'এখনো খাবারের কোনো চিহ্ন নেই।','Run':'চালান','from the project root in XAMPP Shell, then reload this map. Markers use approximate area centres.':'XAMPP Shell-এ প্রজেক্টের মূল ফোল্ডার থেকে চালান, তারপর মানচিত্রটি রিলোড করুন। চিহ্নগুলো আনুমানিক এলাকার কেন্দ্র দেখায়।',
    'Pickup &':'সংগ্রহ ও','delivery tracking.':'ডেলিভারি ট্র্যাকিং।','Record status changes, optional GPS coordinates and photo proof. This is not live GPS tracking.':'অবস্থার পরিবর্তন, ঐচ্ছিক GPS স্থানাঙ্ক ও ছবির প্রমাণ সংরক্ষণ করুন। এটি লাইভ GPS ট্র্যাকিং নয়।','DELIVERY #':'ডেলিভারি #','FROM':'থেকে','TO':'প্রতি','VOLUNTEER':'স্বেচ্ছাসেবক','Next status':'পরবর্তী অবস্থা','Proof photo (JPG/PNG/WEBP, max 3 MB)':'প্রমাণের ছবি (JPG/PNG/WEBP, সর্বোচ্চ ৩ MB)','GPS latitude (optional)':'GPS অক্ষাংশ (ঐচ্ছিক)','GPS longitude (optional)':'GPS দ্রাঘিমাংশ (ঐচ্ছিক)','Update delivery ↗':'ডেলিভারি আপডেট ↗','View delivery proof photo':'ডেলিভারির প্রমাণের ছবি দেখুন','View saved GPS point on OpenStreetMap ↗':'OpenStreetMap-এ সংরক্ষিত GPS অবস্থান দেখুন ↗','No deliveries yet':'এখনো কোনো ডেলিভারি নেই','Accepted donation requests will appear here when a delivery record exists.':'ডেলিভারির রেকর্ড তৈরি হলে গৃহীত দানের অনুরোধ এখানে দেখা যাবে।',
    'Area / community name':'এলাকা / কমিউনিটির নাম','Ward (optional)':'ওয়ার্ড (ঐচ্ছিক)','People in need':'প্রয়োজনমতো সহায়তাপ্রার্থী মানুষ','Meals required':'প্রয়োজনীয় খাবার','Submit demand for verification ↗':'যাচাইয়ের জন্য চাহিদা জমা দিন ↗','Save changes':'পরিবর্তন সংরক্ষণ','Back':'ফিরে যান','You can edit a report while it is still waiting for admin verification.':'অ্যাডমিন যাচাইয়ের অপেক্ষায় থাকা রিপোর্ট সম্পাদনা করতে পারবেন।',
    'Beneficiary':'সুবিধাভোগী','management.':'ব্যবস্থাপনা।','Add beneficiary / household':'সুবিধাভোগী / পরিবার যোগ করুন','NGO owner':'এনজিও মালিক','Select NGO':'এনজিও নির্বাচন করুন','Display name / reference':'প্রদর্শিত নাম / রেফারেন্স','Household size':'পরিবারের সদস্য সংখ্যা','Area / community':'এলাকা / কমিউনিটি','Contact phone (optional)':'যোগাযোগের ফোন (ঐচ্ছিক)','Notes (avoid sensitive details)':'নোট (সংবেদনশীল তথ্য দেবেন না)','Save beneficiary':'সুবিধাভোগী সংরক্ষণ','AUTHORIZED RECORDS':'অনুমোদিত রেকর্ড','Beneficiaries':'সুবিধাভোগীরা','Reference':'রেফারেন্স','Household':'পরিবার','Details':'বিস্তারিত','No beneficiary records yet.':'এখনো কোনো সুবিধাভোগীর রেকর্ড নেই।','Add assessed need':'মূল্যায়ন করা চাহিদা যোগ করুন','Quantity needed':'প্রয়োজনীয় পরিমাণ','Priority':'অগ্রাধিকার','Record need':'চাহিদা রেকর্ড করুন','Assessed needs':'মূল্যায়িত চাহিদা','No needs recorded.':'কোনো চাহিদা রেকর্ড করা হয়নি।','Link an existing distribution':'বিদ্যমান বিতরণের সঙ্গে যুক্ত করুন','Distribution record':'বিতরণের রেকর্ড','Select distribution':'বিতরণ নির্বাচন করুন','Meals allocated':'বরাদ্দ করা খাবার','Link distribution':'বিতরণ যুক্ত করুন','Distribution history':'বিতরণের ইতিহাস','No distribution linked to this beneficiary yet.':'এই সুবিধাভোগীর সঙ্গে এখনো কোনো বিতরণ যুক্ত নেই।',
    'Record food':'খাবার রেকর্ড করুন','distribution.':'বিতরণ।','Donation request':'দানের অনুরোধ','Select a donation':'দান নির্বাচন করুন','Meals distributed':'বিতরণ করা খাবার','Beneficiary count':'সুবিধাভোগীর সংখ্যা','Distribution proof photo (optional)':'বিতরণের প্রমাণের ছবি (ঐচ্ছিক)','Save distribution record ↗':'বিতরণের রেকর্ড সংরক্ষণ ↗','DISTRIBUTION HISTORY':'বিতরণের ইতিহাস','Recorded distributions':'রেকর্ড করা বিতরণ','No distribution records yet.':'এখনো কোনো বিতরণের রেকর্ড নেই।',
    'Subject':'বিষয়','Related food listing ID (optional)':'সংশ্লিষ্ট খাবারের তালিকা ID (ঐচ্ছিক)','Reported by':'রিপোর্ট করেছেন','Related':'সংশ্লিষ্ট','No complaints yet.':'এখনো কোনো অভিযোগ নেই।','Subject':'বিষয়','Details':'বিস্তারিত','Submit complaint':'অভিযোগ জমা দিন',
    'Route planner.':'রুট পরিকল্পনা।','Donation journey.':'দানের যাত্রাপথ।','View history':'ইতিহাস দেখুন','No deliveries visible for this account.':'এই অ্যাকাউন্টে কোনো ডেলিভারি দেখা যাচ্ছে না।','event history':'ইভেন্টের ইতিহাস','Recorded by':'রেকর্ড করেছেন','No status history has been recorded for this delivery yet.':'এই ডেলিভারির কোনো অবস্থার ইতিহাস এখনো রেকর্ড করা হয়নি।','Hash-linked integrity events':'হ্যাশ-সংযুক্ত অখণ্ডতার ইভেন্ট','Event':'ইভেন্ট','Timestamp':'সময়চিহ্ন','Previous hash':'আগের হ্যাশ','Event hash':'ইভেন্ট হ্যাশ',
    'Area-wise needs':'এলাকাভিত্তিক চাহিদা','Edit demand':'চাহিদা সম্পাদনা','report.':'রিপোর্ট।','Urgency':'জরুরিতা','Notes / dietary needs':'নোট / খাদ্যসংক্রান্ত প্রয়োজন','Back':'ফিরে যান','No completed requests in this period.':'এই সময়ে কোনো অনুরোধ সম্পন্ন হয়নি।','Illustrative CO₂e estimate':'আনুমানিক CO₂e হিসাব','Methodology & limitations':'পদ্ধতি ও সীমাবদ্ধতা','No completed donations from verified donors yet.':'যাচাইকৃত দাতাদের কোনো সম্পন্ন দান এখনো নেই।','No active emergency broadcasts':'কোনো সক্রিয় জরুরি ঘোষণা নেই',
    'English':'English','বাংলা':'বাংলা','Rescue Food. Share Hope.':'খাবার বাঁচাই, আশার আলো ছড়াই।','Find available food':'উপলব্ধ খাবার খুঁজুন','Donate food with care →':'ভালোবাসা দিয়ে খাবার দান করুন →','A LITTLE FOOD. A LOT OF HOPE.':'একটু খাবার · অনেকটা আশা','One meal.':'এক মুঠো খাবার।','A little relief.':'একটুখানি স্বস্তি।','A big change.':'এক বড় পরিবর্তন।','Surplus food can bring comfort, dignity and hope to someone who needs it. We connect food donors, NGOs and volunteers to make every safe meal count.':'আপনার উদ্বৃত্ত খাবার কারও জীবনে এনে দিতে পারে স্বস্তি, মর্যাদা আর নতুন আশা। নিরাপদ খাবার সঠিক মানুষের কাছে পৌঁছে দিতে আমরা যুক্ত করি দাতা, এনজিও ও স্বেচ্ছাসেবকদের।','Every meal saved can make someone’s day better.':'বাঁচানো প্রতিটি খাবার কারও দিনটা বদলে দিতে পারে।','Food Rescue Bangladesh':'FoodRescue বাংলাদেশ'
  };
  const skipTags = new Set(['SCRIPT','STYLE','NOSCRIPT','TEXTAREA','INPUT','OPTION']);
  function currentLanguage() { try { return localStorage.getItem(STORAGE_KEY) || 'en'; } catch (_) { return 'en'; } }
  function translateAttributes(root, lang) {
    const nodes = [];
    if (root.nodeType === 1 && root.matches && root.matches('[data-en][data-bn]')) nodes.push(root);
    if (root.querySelectorAll) nodes.push(...root.querySelectorAll('[data-en][data-bn]'));
    nodes.forEach(el => { const value = el.getAttribute('data-' + lang); if (value != null) el.innerHTML = value; });
    const attrs = { placeholder: true, title: true, 'aria-label': true };
    const all = [];
    if (root.nodeType === 1) all.push(root);
    if (root.querySelectorAll) all.push(...root.querySelectorAll('[placeholder],[title],[aria-label]'));
    all.forEach(el => Object.keys(attrs).forEach(attr => {
      const original = el.getAttribute(attr); if (!original) return;
      if (!el.dataset['langOriginal' + attr.replace(/[^a-z]/gi,'')]) el.dataset['langOriginal' + attr.replace(/[^a-z]/gi,'')] = original;
      const base = el.dataset['langOriginal' + attr.replace(/[^a-z]/gi,'')];
      if (lang === 'bn' && translations[base]) el.setAttribute(attr, translations[base]); else el.setAttribute(attr, base);
    }));
  }
  function translateText(root, lang) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = []; let node;
    while ((node = walker.nextNode())) {
      const parent = node.parentElement;
      if (!parent || skipTags.has(parent.tagName) || parent.closest('[data-en][data-bn]') || parent.isContentEditable) continue;
      const raw = node.nodeValue || '', trimmed = raw.trim();
      if (!trimmed) continue;
      if (!node.__frOriginal) node.__frOriginal = trimmed;
      const original = node.__frOriginal;
      if (lang === 'bn' && translations[original]) nodes.push([node, raw.replace(trimmed, translations[original])]);
      else nodes.push([node, raw.replace(trimmed, original)]);
    }
    nodes.forEach(([n, value]) => { if (n.nodeValue !== value) n.nodeValue = value; });
  }
  function applyLanguage(lang) {
    lang = lang === 'bn' ? 'bn' : 'en';
    document.documentElement.lang = lang;
    translateAttributes(document.body, lang);
    translateText(document.body, lang);
    document.querySelectorAll('.lang-btn').forEach(btn => {
      const active = btn.dataset.lang === lang; btn.classList.toggle('active', active); btn.setAttribute('aria-pressed', String(active));
    });
    try { localStorage.setItem(STORAGE_KEY, lang); } catch (_) {}
  }
  function initSiteLanguage() {
    // Delegated handler keeps the switch working even if shared header markup is re-rendered.
    document.addEventListener('click', event => {
      const button = event.target && event.target.closest ? event.target.closest('.lang-btn[data-lang]') : null;
      if (!button) return;
      event.preventDefault();
      applyLanguage(button.dataset.lang);
    });
    applyLanguage(currentLanguage());
    if (!document.body || !window.MutationObserver) return;
    const observer = new MutationObserver(records => {
      const lang = currentLanguage();
      records.forEach(record => {
        record.addedNodes && record.addedNodes.forEach(n => {
          if (n.nodeType === 1 || n.nodeType === 3) {
            if (n.nodeType === 1) translateAttributes(n, lang);
            const root = n.nodeType === 1 ? n : n.parentElement;
            if (root) translateText(root, lang);
          }
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSiteLanguage, { once: true });
  else initSiteLanguage();
})();
