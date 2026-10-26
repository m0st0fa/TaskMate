<?php
// TaskMate homepage (redesign). Swap these arrays for MySQL queries when ready.

$pageTitle = 'Home | TaskMate';

$categories = [
    ['Home Cleaning',        '#E2F0E8', '#08523A', 'broom'],
    ['Moving & Packing',     '#FBE8E2', '#8F2A17', 'box'],
    ['Repair & Maintenance', '#FFF4D6', '#7A5A00', 'wrench'],
    ['Tutoring & Education', '#EFE8F7', '#4B2E83', 'cap'],
    ['Design & Creative',    '#FBE8E2', '#8F2A17', 'palette'],
    ['Delivery & Shopping',  '#E3EEF8', '#1F4E79', 'cart'],
    ['Event Help',           '#E2F0E8', '#08523A', 'calendar'],
    ['IT & Tech Support',    '#EFE8F7', '#4B2E83', 'laptop'],
    ['Others',               '#ECE9DF', '#3E4F47', 'dots'],
];

$tasks = [
    ['House Cleaning Needed', 'Uttara, Dhaka',    '800 - 1,200',   'Home Cleaning',        '#E2F0E8', '#08523A', 'assets/task-cleaning.jpg'],
    ['Help with Moving',      'Mirpur, Dhaka',    '1,500 - 2,000', 'Moving & Packing',     '#FBE8E2', '#8F2A17', 'assets/task-moving.jpg'],
    ['Laptop Repair',         'Dhanmondi, Dhaka', '500 - 1,000',   'Repair & Maintenance', '#FFF4D6', '#7A5A00', 'assets/task-laptop.jpg'],
    ['Math Tutor (O Level)',  'Gulshan, Dhaka',   '1,000 - 1,500', 'Tutoring & Education', '#EFE8F7', '#4B2E83', 'assets/task-tutor.jpg'],
];

// Sample helpers. Replace with a query on your helpers table.
$helpers = [
    ['Rahim', 'R', 'Delivery and pickup',      96, '4.9', '0.8', 127, '250', 'Top Helper', '#DCEBE2', '#08523A'],
    ['Nusrat', 'N', 'Home and deep cleaning',  93, '4.8', '1.2', 84,  '500', 'Expert',     '#FBE8E2', '#8F2A17'],
    ['Karim', 'K', 'Electrician',              90, '4.7', '1.6', 62,  '300', 'Trusted',    '#FFF4D6', '#7A5A00'],
    ['Sakib', 'S', 'Computer and Wi-Fi setup', 88, '4.5', '2.4', 31,  '350', 'Trusted',    '#E3EEF8', '#1F4E79'],
];

$offers = [
    ['Rahim', 'R', '4.9', '0.8 km', '220', true,  '#DCEBE2', '#08523A'],
    ['Karim', 'K', '4.7', '1.6 km', '250', false, '#FBE8E2', '#8F2A17'],
    ['Sakib', 'S', '4.5', '2.4 km', '180', false, '#FFF4D6', '#7A5A00'],
];

$trust = [
    ['Verified identities',        'Email, phone and NID checks, with a visible trust score on every profile.', 'shield'],
    ['Payment held, then released', 'Your money moves to the helper only after you confirm the task is done.',   'wallet'],
    ['Ratings both ways',          'Customers and helpers rate each other on quality, timing and behavior.',    'star'],
    ['A real dispute center',      'Upload evidence and let an admin decide on a refund or release.',           'chat'],
];

function icon($name, $color, $size = 28) {
    $p = [
        'broom'   => '<path d="M19 3l-6 6M9 11l4 4M4 20c0-4 2-7 5-9l4 4c-2 3-5 5-9 5z"/>',
        'box'     => '<path d="M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10"/>',
        'wrench'  => '<path d="M14.7 6.3a4 4 0 0 0 5 5L21 12.6 12.6 21a2.1 2.1 0 0 1-3-3L18 9.6zM3 21l4-4"/>',
        'cap'     => '<path d="M2 9l10-5 10 5-10 5zM6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"/>',
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2h2a4 4 0 0 0 4-4c0-5-4-10-9-10z"/><circle cx="8" cy="11" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16" cy="11" r="1"/>',
        'cart'    => '<path d="M3 4h2l2 11h11l2-8H6"/><circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/>',
        'calendar'=> '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M8 14h2M12 14h2M16 14h2M8 17h2M12 17h2"/>',
        'laptop'  => '<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 20h20"/>',
        'dots'    => '<circle cx="6" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="18" cy="12" r="1.6"/>',
        'shield'  => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'wallet'  => '<path d="M3 7h18v12H3zM3 7l3-3h12v3M16 13h3"/>',
        'star'    => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'chat'    => '<path d="M4 5h16v11H9l-5 4z"/>',
        'check'   => '<path d="M5 12l4 4 10-10"/>',
        'pin'     => '<path d="M12 21s7-6.2 7-11a7 7 0 0 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/>',
        'search'  => '<path d="M21 21l-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"/>',
        'doc'     => '<path d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6"/>',
        'calbook' => '<path d="M4 6h16v14H4zM4 10h16M9 3v5M15 3v5"/>',
    ][$name];
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="' . $color . '" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

$starSvg = '<svg viewBox="0 0 24 24" width="14" height="14" fill="#E0A100" aria-hidden="true"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg>';

require __DIR__ . '/includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=DM+Sans:wght@400;500;700&family=Hind+Siliguri:wght@400;500;600&display=swap');

.tm-home{
    --ink:#13201A; --muted:#5B6B63; --soft:#3E4F47; --line:#E2DFD5; --bg:#F6F4EE;
    --green:#0B6B4A; --green-d:#08523A; --green-l:#E2F0E8; --coral:#C93D25; --dark:#0F2A21;
    font-family:'DM Sans','Hind Siliguri',sans-serif; color:var(--ink); background:var(--bg);
    font-size:16px; line-height:1.5;
}
.tm-home *{box-sizing:border-box}
.tm-home a{text-decoration:none}
.tm-home h1,.tm-home h2,.tm-home h3{margin:0;font-family:'Bricolage Grotesque','Hind Siliguri',sans-serif;letter-spacing:-.03em;line-height:1.1}
.tm-home p{margin:0}
.tm-wrap{max-width:1200px;margin:0 auto;padding:0 32px}
.tm-sec{padding:80px 0}
.tm-white{background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.tm-h2{font-size:44px}
.tm-lead{margin-top:12px;font-size:18px;color:var(--muted);max-width:640px}
.tm-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px}
.tm-link{font-weight:700;color:var(--green)}
.tm-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;font-weight:700;border-radius:14px;padding:16px 28px;font-size:18px;border:1.5px solid transparent;cursor:pointer;font-family:inherit}
.tm-btn-coral{background:var(--coral);color:#fff}
.tm-btn-green{background:var(--green);color:#fff}
.tm-btn-line{background:#fff;color:var(--ink);border-color:var(--ink)}
.tm-btn-dark{background:var(--ink);color:#fff}
.tm-btn-white{background:#fff;color:var(--green-d)}

/* hero */
.tm-hero .tm-wrap{display:flex;flex-wrap:wrap;align-items:center;gap:56px;padding-top:72px;padding-bottom:88px}
.tm-hero-text{flex:1 1 520px;min-width:0}
.tm-pill{display:inline-flex;align-items:center;gap:8px;background:var(--green-l);color:var(--green-d);font-weight:700;font-size:14px;padding:7px 14px;border-radius:999px}
.tm-hero h1{font-size:68px;margin-top:20px;line-height:1.02;letter-spacing:-.035em}
.tm-hero h1 em{font-style:normal;color:var(--green)}
.tm-hero-p{margin-top:20px;font-size:20px;color:var(--soft);max-width:560px}
.tm-hero-bn{margin-top:8px;font-size:18px;color:var(--muted)}
.tm-search{margin-top:32px;max-width:640px;background:#fff;border:1px solid #D9D5C8;border-radius:18px;padding:8px;display:flex;flex-wrap:wrap;align-items:center;gap:4px;box-shadow:0 10px 30px rgba(19,32,26,.08)}
.tm-search-field{flex:1 1 240px;display:flex;align-items:center;gap:10px;padding:0 14px;min-height:52px}
.tm-search input{border:0;outline:0;background:transparent;font:inherit;width:100%;color:var(--ink)}
.tm-search-loc{display:flex;align-items:center;gap:8px;padding:0 10px;min-height:52px;border-left:1px solid var(--line)}
.tm-search select{border:0;outline:0;background:transparent;font:inherit;font-weight:500;color:var(--soft)}
.tm-search button{background:var(--ink);color:#fff;border:0;font:inherit;font-weight:700;min-height:52px;padding:0 26px;border-radius:12px;cursor:pointer}
.tm-cta-row{margin-top:24px;display:flex;flex-wrap:wrap;gap:14px}
.tm-trust-row{list-style:none;margin:32px 0 0;padding:0;display:flex;flex-wrap:wrap;gap:10px 24px;color:var(--soft);font-size:15px;font-weight:500}
.tm-trust-row li{display:flex;align-items:center;gap:8px}

.tm-hero-visual{flex:1 1 440px;min-width:0;position:relative;display:flex;justify-content:center;padding:24px 0}
.tm-hero-visual:before{content:"";position:absolute;width:440px;height:440px;max-width:100%;border-radius:50%;background:#DCEBE2;top:50%;left:50%;transform:translate(-50%,-50%)}
.tm-offer-card{position:relative;width:100%;max-width:480px;background:#fff;border:1px solid var(--line);border-radius:22px;padding:24px;box-shadow:0 24px 60px rgba(19,32,26,.16)}
.tm-oc-top{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:14px;color:var(--muted)}
.tm-badge{background:var(--green-l);color:var(--green-d);font-weight:700;font-size:13px;padding:5px 12px;border-radius:999px}
.tm-offer-card h2{margin-top:14px;font-size:24px;line-height:1.2;letter-spacing:-.02em}
.tm-oc-meta{margin-top:14px;display:flex;flex-wrap:wrap;gap:8px 18px;color:var(--soft);font-size:14px;font-weight:500;padding-bottom:16px;border-bottom:1px solid var(--line)}
.tm-oc-list{margin-top:14px;display:flex;flex-direction:column;gap:10px}
.tm-oc-row{display:flex;align-items:center;gap:12px;border:1px solid var(--line);background:#fff;border-radius:14px;padding:12px 14px}
.tm-oc-row.best{border-color:var(--green);background:#F1F8F4}
.tm-av{flex:none;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:700;font-family:'Bricolage Grotesque',sans-serif}
.tm-oc-row .tm-av{width:40px;height:40px}
.tm-oc-info{flex:1;min-width:0}
.tm-oc-info b{display:block}
.tm-oc-info span{display:flex;align-items:center;gap:6px;color:var(--muted);font-size:14px}
.tm-price{font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:20px}
.tm-oc-btn{margin-top:16px;display:block;text-align:center;background:var(--green);color:#fff;font-weight:700;padding:13px 20px;border-radius:12px}
.tm-held{position:absolute;right:-14px;bottom:-22px;background:var(--ink);color:#fff;border-radius:14px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 12px 30px rgba(19,32,26,.3);line-height:1.25}
.tm-held b{display:block;font-size:14px}
.tm-held small{font-size:12px;color:#B9CBC2}

/* two ways */
.tm-ways{margin-top:40px;display:flex;flex-wrap:wrap;gap:24px}
.tm-way{flex:1 1 460px;border-radius:24px;padding:36px;display:flex;flex-direction:column;gap:16px}
.tm-way-coral{background:#FBE8E2}
.tm-way-green{background:var(--green-l)}
.tm-way-top{display:flex;align-items:center;gap:12px}
.tm-way-ic{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center}
.tm-way-tag{font-weight:700;font-size:14px;letter-spacing:.04em;text-transform:uppercase}
.tm-way h3{font-size:32px}
.tm-way p{font-size:17px;color:var(--soft)}
.tm-way-ex{background:#fff;border-radius:14px;padding:14px 16px;font-size:15px;color:var(--soft)}
.tm-chips{display:flex;flex-wrap:wrap;gap:8px}
.tm-chips span{background:#fff;border-radius:999px;padding:6px 14px;font-size:14px;font-weight:500}

/* categories */
.tm-cats{margin-top:32px;display:flex;flex-wrap:wrap;gap:16px}
.tm-cat{flex:1 1 260px;background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px;display:flex;align-items:center;gap:16px;color:var(--ink);transition:border-color .15s, transform .15s}
.tm-cat:hover{border-color:var(--green);transform:translateY(-2px)}
.tm-cat-ic{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex:none}
.tm-cat b{display:block;font-size:17px}

/* how */
.tm-steps{margin-top:40px;display:flex;flex-wrap:wrap;gap:24px}
.tm-step{flex:1 1 300px;display:flex;flex-direction:column;gap:14px}
.tm-step-top{display:flex;align-items:center;gap:14px}
.tm-num{width:52px;height:52px;border-radius:50%;background:var(--ink);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:22px;flex:none}
.tm-step-top i{flex:1;border-top:2px dashed #CFCABA}
.tm-step h3{font-size:26px}
.tm-step p{color:var(--soft);font-size:17px}

/* AI */
.tm-ai{background:var(--dark);color:#fff}
.tm-ai .tm-wrap{display:flex;flex-wrap:wrap;align-items:center;gap:56px;padding-top:88px;padding-bottom:88px}
.tm-ai-text{flex:1 1 440px;min-width:0}
.tm-ai-tag{display:inline-block;background:#1D4A3A;color:#9FE0C1;font-weight:700;font-size:13px;padding:6px 14px;border-radius:999px;letter-spacing:.04em;text-transform:uppercase}
.tm-ai h2{margin-top:18px;font-size:46px;line-height:1.08}
.tm-ai-p{margin-top:16px;font-size:18px;color:#B9CBC2;max-width:520px}
.tm-ai ul{list-style:none;margin:24px 0 0;padding:0;display:flex;flex-direction:column;gap:10px;color:#E2F0E8}
.tm-ai li{display:flex;align-items:center;gap:10px}
.tm-ai-card{flex:1 1 440px;min-width:0;background:#fff;color:var(--ink);border-radius:24px;padding:28px;display:flex;flex-direction:column;gap:18px}
.tm-ai-in{background:var(--bg);border-radius:16px;padding:16px 18px}
.tm-ai-in small{display:block;font-size:13px;font-weight:700;color:var(--muted);letter-spacing:.04em;text-transform:uppercase}
.tm-ai-in div{margin-top:6px;font-size:19px}
.tm-ai-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid var(--line);border-radius:14px;padding:14px 18px}
.tm-ai-row span{color:var(--muted);font-weight:500}
.tm-ai-row b{font-size:18px;color:var(--green-d)}

/* recent tasks */
.tm-tasks{margin-top:32px;display:flex;flex-wrap:wrap;gap:20px}
.tm-task{flex:1 1 250px;background:#fff;border:1px solid var(--line);border-radius:22px;overflow:hidden;display:flex;flex-direction:column}
.tm-task-img{width:100%;height:150px;object-fit:cover;background:#E5E2D6;display:block}
.tm-task-body{padding:18px 20px 20px;display:flex;flex-direction:column;gap:8px;flex:1}
.tm-task-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
.tm-task h3{font-size:20px;line-height:1.2}
.tm-fav{width:44px;height:44px;margin:-8px -10px 0 0;border:0;background:transparent;font-size:22px;color:var(--muted);cursor:pointer;border-radius:50%}
.tm-fav:hover{background:var(--bg);color:var(--coral)}
.tm-task-meta{display:flex;align-items:center;gap:6px;color:var(--muted);font-size:14px}
.tm-task-price{font-weight:700;color:var(--ink);font-size:16px}
.tm-tag{align-self:flex-start;font-size:13px;font-weight:700;padding:4px 12px;border-radius:999px;margin-top:auto}

/* helpers */
.tm-helpers{margin-top:32px;display:flex;flex-wrap:wrap;gap:20px}
.tm-helper{flex:1 1 250px;background:#fff;border:1px solid var(--line);border-radius:22px;padding:24px;display:flex;flex-direction:column;gap:14px}
.tm-helper-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
.tm-helper .tm-av{width:64px;height:64px;font-size:24px}
.tm-helper h3{font-size:20px;display:flex;align-items:center;gap:6px;letter-spacing:-.02em}
.tm-helper small{color:var(--muted);font-size:15px;display:block;margin-top:2px}
.tm-helper-stats{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;font-size:14px;color:var(--soft)}
.tm-helper-stats b{display:inline-flex;align-items:center;gap:4px;color:var(--ink)}
.tm-helper-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;border-top:1px solid var(--line);padding-top:14px}
.tm-helper-foot small{font-size:12px;margin:0}
.tm-level{background:var(--bg);border:1px solid var(--line);font-size:13px;font-weight:700;padding:5px 12px;border-radius:999px}
.tm-view{display:block;text-align:center;background:var(--green);color:#fff;font-weight:700;padding:12px 16px;border-radius:12px}

/* trust */
.tm-trust{margin-top:40px;display:flex;flex-wrap:wrap;gap:20px}
.tm-trust-card{flex:1 1 250px;background:var(--bg);border-radius:20px;padding:26px;display:flex;flex-direction:column;gap:12px}
.tm-trust-ic{width:48px;height:48px;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center}
.tm-trust-card h3{font-size:22px}
.tm-trust-card p{color:var(--soft)}
.tm-pay{margin-top:32px;display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.tm-pay b{color:var(--muted)}
.tm-pay span{border:1.5px solid var(--ink);border-radius:10px;padding:8px 18px;font-weight:700}

/* helper + business */
.tm-dual{display:flex;flex-wrap:wrap;gap:24px}
.tm-dual-card{border-radius:28px;padding:44px;display:flex;flex-direction:column;gap:18px;color:#fff}
.tm-dual-card h2{font-size:40px}
.tm-dual-card p{font-size:18px}
.tm-dual-eyebrow{font-weight:700;font-size:14px;letter-spacing:.04em;text-transform:uppercase}
.tm-levels{display:flex;flex-wrap:wrap;gap:8px}
.tm-levels span{background:var(--green-d);border-radius:999px;padding:6px 16px;font-weight:700;font-size:14px}
.tm-dual-card .tm-btn{align-self:flex-start;font-size:17px;padding:14px 28px;border-radius:12px}

/* final CTA */
.tm-final{background:var(--green);color:#fff;text-align:center}
.tm-final .tm-wrap{padding-top:72px;padding-bottom:72px;display:flex;flex-direction:column;align-items:center;gap:18px}
.tm-final h2{font-size:44px}
.tm-final p{font-size:18px;color:#D7EFE2}

@media (max-width:768px){
    .tm-wrap{padding:0 20px}
    .tm-sec{padding:56px 0}
    .tm-hero .tm-wrap{padding-top:36px;padding-bottom:56px;gap:36px}
    .tm-hero h1{font-size:40px}
    .tm-hero-p{font-size:17px}
    .tm-h2,.tm-ai h2,.tm-final h2,.tm-dual-card h2{font-size:30px}
    .tm-way,.tm-dual-card{padding:26px}
    .tm-way h3{font-size:26px}
    .tm-btn{width:100%}
    .tm-dual-card .tm-btn{align-self:stretch}
    .tm-search-loc{border-left:0;border-top:1px solid var(--line);flex:1 1 100%}
    .tm-search button{flex:1 1 100%}
    .tm-held{right:8px}
}
</style>

<div class="tm-home">

<!-- HERO -->
<section class="tm-hero">
    <div class="tm-wrap">
        <div class="tm-hero-text">
            <span class="tm-pill"><?= icon('pin', '#08523A', 16) ?> Made for Bangladesh</span>
            <h1>Get everyday tasks done by <em>people nearby.</em></h1>
            <p class="tm-hero-p">Post what you need and compare offers, or book a ready-made service. From a document delivery to a deep clean.</p>
            <p class="tm-hero-bn">আপনার দরকারি কাজ, আশেপাশের ভরসাযোগ্য হেলপারদের হাতে।</p>

            <form class="tm-search" action="browse.php" method="get" role="search">
                <div class="tm-search-field">
                    <?= icon('search', '#5B6B63', 20) ?>
                    <label for="tm-q" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">What do you need help with?</label>
                    <input id="tm-q" type="text" name="q" placeholder="Try: clean my flat, fix AC, deliver a document">
                </div>
                <div class="tm-search-loc">
                    <?= icon('pin', '#0B6B4A', 18) ?>
                    <label for="tm-city" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">City</label>
                    <select id="tm-city" name="city">
                        <option>Dhaka</option><option>Chattogram</option><option>Sylhet</option><option>Khulna</option><option>Rajshahi</option>
                    </select>
                </div>
                <button type="submit">Search</button>
            </form>

            <div class="tm-cta-row">
                <a href="/pages/post-task.php" class="tm-btn tm-btn-coral">Post a Task <?= icon('check', '#fff', 20) ?></a>
                <a href="browse.php" class="tm-btn tm-btn-line">Find a Service</a>
            </div>

            <ul class="tm-trust-row">
                <li><?= icon('check', '#0B6B4A', 18) ?> NID-verified helpers</li>
                <li><?= icon('check', '#0B6B4A', 18) ?> bKash, Nagad, card or cash</li>
                <li><?= icon('check', '#0B6B4A', 18) ?> Payment held until done</li>
            </ul>
        </div>

        <div class="tm-hero-visual">
            <div class="tm-offer-card">
                <div class="tm-oc-top">
                    <span class="tm-badge">Offers received</span>
                    <span>Posted 12 min ago</span>
                </div>
                <h2>I need a document delivered from Uttara to Farmgate</h2>
                <div class="tm-oc-meta">
                    <span>Uttara → Farmgate</span>
                    <span>Today, 4:00 PM</span>
                    <span style="color:#08523A;font-weight:700">Budget ৳ 250</span>
                </div>
                <div class="tm-oc-list">
                    <?php foreach ($offers as [$name, $ini, $rating, $dist, $price, $best, $bg, $fg]): ?>
                        <div class="tm-oc-row<?= $best ? ' best' : '' ?>">
                            <span class="tm-av" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= $ini ?></span>
                            <span class="tm-oc-info">
                                <b><?= htmlspecialchars($name) ?></b>
                                <span><?= $starSvg ?> <?= $rating ?> · <?= $dist ?> away</span>
                            </span>
                            <span class="tm-price">৳ <?= $price ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="browse.php" class="tm-oc-btn">Compare and accept an offer</a>
                
            </div>
        </div>
    </div>
</section>

<!-- TWO WAYS -->
<section class="tm-sec tm-white">
    <div class="tm-wrap">
        <h2 class="tm-h2">Two ways to get it done</h2>
        <p class="tm-lead">Not sure what it should cost? Post a task and let helpers bid. Know exactly what you want? Book it in a minute.</p>
        <div class="tm-ways">
            <div class="tm-way tm-way-coral">
                <div class="tm-way-top">
                    <span class="tm-way-ic" style="background:#C93D25"><?= icon('doc', '#fff', 26) ?></span>
                    <span class="tm-way-tag" style="color:#8F2A17">Get offers</span>
                </div>
                <h3>Post a Task</h3>
                <p>Describe your requirement, set a budget, location and time. Helpers send offers and you pick the best one by price, rating and distance.</p>
                <div class="tm-way-ex"><b>Example:</b> Document delivery, Uttara to Farmgate. Budget ৳ 250.</div>
                <div><a href="/pages/post-task.php" class="tm-btn tm-btn-coral" style="font-size:16px;padding:14px 26px;border-radius:12px">Post a Task</a></div>
            </div>
            <div class="tm-way tm-way-green">
                <div class="tm-way-top">
                    <span class="tm-way-ic" style="background:#0B6B4A"><?= icon('calbook', '#fff', 26) ?></span>
                    <span class="tm-way-tag" style="color:#08523A">Book instantly</span>
                </div>
                <h3>Book a Service</h3>
                <p>Choose a predefined service, your location, a date and time. See the price up front and confirm your booking.</p>
                <div class="tm-chips">
                    <span>Cleaning</span><span>Electrician</span><span>Plumber</span><span>AC Service</span><span>Computer Repair</span><span>Moving</span><span>Laundry</span><span>Pest Control</span>
                </div>
                <div><a href="browse.php" class="tm-btn tm-btn-green" style="font-size:16px;padding:14px 26px;border-radius:12px">Find a Service</a></div>
            </div>
        </div>
    </div>
</section>

<!-- CATEGORIES -->
<section class="tm-sec">
    <div class="tm-wrap">
        <div class="tm-head">
            <div>
                <h2 class="tm-h2">Popular Categories</h2>
                <p class="tm-lead">Pick a category to see tasks and helpers near you.</p>
            </div>
            <a href="categories.php" class="tm-link">View All →</a>
        </div>
        <div class="tm-cats">
            <?php foreach ($categories as [$label, $bg, $fg, $ic]): ?>
                <a href="browse.php?category=<?= urlencode($label) ?>" class="tm-cat">
                    <span class="tm-cat-ic" style="background:<?= $bg ?>"><?= icon($ic, $fg) ?></span>
                    <b><?= htmlspecialchars($label) ?></b>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="tm-sec tm-white">
    <div class="tm-wrap">
        <h2 class="tm-h2">How TaskMate Works</h2>
        <div class="tm-steps">
            <div class="tm-step">
                <div class="tm-step-top"><span class="tm-num">1</span><i></i></div>
                <h3>Post a Task</h3>
                <p>Tell us what you need help with and set your budget, or pick a ready-made service.</p>
            </div>
            <div class="tm-step">
                <div class="tm-step-top"><span class="tm-num">2</span><i></i></div>
                <h3>Get Offers</h3>
                <p>Compare offers from verified helpers by price, rating and distance. Chat before you decide.</p>
            </div>
            <div class="tm-step">
                <div class="tm-step-top"><span class="tm-num">3</span><i></i></div>
                <h3>Complete the Task</h3>
                <p>Track your helper, pay securely, then rate each other once the work is done.</p>
            </div>
        </div>
    </div>
</section>

<!-- AI ASSISTANT -->
<section class="tm-ai">
    <div class="tm-wrap">
        <div class="tm-ai-text">
            <span class="tm-ai-tag">AI task assistant</span>
            <h2>Just say what you need. In Bangla, English, or both.</h2>
            <p class="tm-ai-p">The assistant spots the service, estimates the time and suggests a fair budget, then writes a clean task description for you.</p>
            <ul>
                <li><?= icon('check', '#7FD1AA', 18) ?> Understands natural Bangla and English</li>
                <li><?= icon('check', '#7FD1AA', 18) ?> Detects the right service category</li>
                <li><?= icon('check', '#7FD1AA', 18) ?> Suggests duration and a fair budget</li>
                <li><?= icon('check', '#7FD1AA', 18) ?> Recommends helpers who fit the job</li>
            </ul>
        </div>
        <div class="tm-ai-card">
            <div class="tm-ai-in"><small>You wrote</small><div>Amar room ta onek messy, clean korar jonno lok lagbe.</div></div>
            <div class="tm-ai-row"><span>Service</span><b>Home Cleaning</b></div>
            <div class="tm-ai-row"><span>Estimated duration</span><b>2–3 hours</b></div>
            <div class="tm-ai-row"><span>Suggested budget</span><b>৳ 500 – ৳ 800</b></div>
            <a href="pages/post-task.php" class="tm-btn tm-btn-coral" style="font-size:17px;padding:15px 20px">Find Helpers</a>
        </div>
    </div>
</section>

<!-- RECENT TASKS -->
<section class="tm-sec">
    <div class="tm-wrap">
        <div class="tm-head">
            <div>
                <h2 class="tm-h2">Recent Tasks</h2>
                <p class="tm-lead">Fresh tasks posted by people near you.</p>
            </div>
            <a href="browse.php" class="tm-link">View All →</a>
        </div>
        <div class="tm-tasks">
            <?php foreach ($tasks as [$title, $loc, $price, $cat, $tagBg, $tagFg, $img]): ?>
                <article class="tm-task">
                    <img src="<?= htmlspecialchars($img) ?>" alt="" class="tm-task-img" onerror="this.removeAttribute('src')">
                    <div class="tm-task-body">
                        <div class="tm-task-top">
                            <h3><?= htmlspecialchars($title) ?></h3>
                            <button type="button" class="tm-fav" aria-label="Save task">♡</button>
                        </div>
                        <div class="tm-task-meta"><?= icon('pin', '#5B6B63', 14) ?> <?= htmlspecialchars($loc) ?></div>
                        <div class="tm-task-price">৳ <?= htmlspecialchars($price) ?></div>
                        <span class="tm-tag" style="background:<?= $tagBg ?>;color:<?= $tagFg ?>"><?= htmlspecialchars($cat) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TRUSTED HELPERS -->
<section class="tm-sec tm-white">
    <div class="tm-wrap">
        <div class="tm-head">
            <div>
                <h2 class="tm-h2">Trusted helpers near you</h2>
                <p class="tm-lead">Ranked by match score: location, skills, rating, price, availability and reliability.</p>
            </div>
            <a href="browse.php" class="tm-link">Browse all helpers →</a>
        </div>
        <div class="tm-helpers">
            <?php foreach ($helpers as [$name, $ini, $skill, $match, $rating, $dist, $done, $price, $level, $bg, $fg]): ?>
                <div class="tm-helper">
                    <div class="tm-helper-top">
                        <span class="tm-av" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= $ini ?></span>
                        <span class="tm-badge"><?= $match ?>% match</span>
                    </div>
                    <div>
                        <h3><?= htmlspecialchars($name) ?> <?= icon('shield', '#0B6B4A', 18) ?></h3>
                        <small><?= htmlspecialchars($skill) ?></small>
                    </div>
                    <div class="tm-helper-stats">
                        <b><?= $starSvg ?> <?= $rating ?></b>
                        <span><?= $dist ?> km away</span>
                        <span><?= $done ?> completed</span>
                    </div>
                    <div class="tm-helper-foot">
                        <div><small>Starting at</small><span class="tm-price" style="font-size:22px">৳ <?= $price ?></span></div>
                        <span class="tm-level"><?= htmlspecialchars($level) ?></span>
                    </div>
                    <a href="browse.php" class="tm-view">View profile</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TRUST AND SAFETY -->
<section class="tm-sec">
    <div class="tm-wrap">
        <h2 class="tm-h2" style="max-width:760px">Built so you can trust the person at your door.</h2>
        <div class="tm-trust">
            <?php foreach ($trust as [$t, $b, $ic]): ?>
                <div class="tm-trust-card">
                    <span class="tm-trust-ic"><?= icon($ic, '#0B6B4A', 26) ?></span>
                    <h3><?= htmlspecialchars($t) ?></h3>
                    <p><?= htmlspecialchars($b) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="tm-pay">
            <b>Pay your way</b>
            <span>bKash</span><span>Nagad</span><span>Card</span><span>Cash</span>
        </div>
    </div>
</section>

<!-- BECOME A HELPER + BUSINESS -->
<section style="padding:0 0 80px">
    <div class="tm-wrap">
        <div class="tm-dual">
            <div class="tm-dual-card" style="flex:1 1 520px;background:#0B6B4A">
                <span class="tm-dual-eyebrow" style="color:#BFE8D2">Earn money with TaskMate</span>
                <h2>Turn your skills and spare hours into income.</h2>
                <p style="color:#D7EFE2">Set your own prices, pick jobs near you and get paid for every finished task. Grow from Beginner to Top Helper.</p>
                <div class="tm-levels"><span>Beginner</span><span>Trusted</span><span>Expert</span><span>Top Helper</span></div>
                <a href="register.php?role=helper" class="tm-btn tm-btn-white">Become a Helper</a>
            </div>
            <div class="tm-dual-card" style="flex:1 1 420px;background:#13201A">
                <span class="tm-dual-eyebrow" style="color:#F2B8A8">TaskMate for Business</span>
                <h2>Recurring help for restaurants, offices, shops and hostels.</h2>
                <p style="color:#C9D3CE">Schedule weekly cleaning, monthly maintenance and regular deliveries. Get a monthly summary and invoices.</p>
                <a href="register.php?role=business" class="tm-btn tm-btn-coral">Create a business account</a>
            </div>
        </div>
    </div>
</section>

<!-- FINAL CTA -->
<section class="tm-final">
    <div class="tm-wrap">
        <h2>Need something done today?</h2>
        <p>Post a task now and let helpers come to you.</p>
        <a href="/pages/post-task.php" class="tm-btn tm-btn-coral">Post a Task <?= icon('check', '#fff', 20) ?></a>
    </div>
</section>

</div>

<?php // footer.php outputs the footer, the single script include, and </body></html>. ?>
<?php require __DIR__ . '/includes/footer.php'; ?>