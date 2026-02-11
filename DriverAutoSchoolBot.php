<?php

// ================== ЗАВАНТАЖЕННЯ .env (без залежностей) ==================
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        putenv("$name=$value");
    }
}

$token = getenv('BOT_TOKEN');
if (!$token) {
    die("⚠️ Не знайдено BOT_TOKEN! Перевірте .env файл або змінні оточення.");
}

// ================== НАСТРОЙКИ ==================
$bot_name   = "DriverAutoSchool_bot";
$curator_id = 761584410;
$access_time = 90 * 24 * 60 * 60;  // 90 днів

// ================== ДАНІ ==================
$data_file = "bot_data.json";
$invite_codes      = [];
$user_access_time  = [];
$user_states       = [];
$curator_reply_to  = [];

function load_data() {
    global $data_file, $invite_codes, $user_access_time, $user_states, $curator_reply_to;
    if (file_exists($data_file)) {
        try {
            $data = json_decode(file_get_contents($data_file), true);
            $invite_codes      = $data['invite_codes']      ?? [];
            $user_access_time  = $data['user_access_time']  ?? [];
            $user_states       = $data['user_states']       ?? [];
            $curator_reply_to  = $data['curator_reply_to']  ?? [];
        } catch (Exception $e) {
            error_log("Помилка завантаження даних: " . $e->getMessage());
        }
    }
}

function save_data() {
    global $data_file, $invite_codes, $user_access_time, $user_states, $curator_reply_to;
    try {
        $data = [
            "invite_codes"      => $invite_codes,
            "user_access_time"  => $user_access_time,
            "user_states"       => $user_states,
            "curator_reply_to"  => $curator_reply_to
        ];
        file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    } catch (Exception $e) {
        error_log("Помилка збереження даних: " . $e->getMessage());
    }
}

load_data();

// ================== ФУНКЦІЇ ДЛЯ TELEGRAM API ==================
function send_message($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    global $token;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $post = ['chat_id' => $chat_id, 'text' => $text];
    if ($reply_markup) $post['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode)   $post['parse_mode']   = $parse_mode;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => $post,
        CURLOPT_RETURNTRANSFER  => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function forward_message($chat_id, $from_chat_id, $message_id) {
    global $token;
    $url = "https://api.telegram.org/bot$token/forwardMessage";
    $post = [
        'chat_id'     => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id'  => $message_id,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
    curl_close($ch);
}

function answer_callback_query($callback_query_id, $text) {
    global $token;
    $url = "https://api.telegram.org/bot$token/answerCallbackQuery";
    $post = ['callback_query_id' => $callback_query_id, 'text' => $text];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
    curl_close($ch);
}

// ================== КЛАВІАТУРИ ==================
function get_main_keyboard() {
    return [
        'keyboard' => [
            [['text' => 'Урок 1'], ['text' => 'Урок 2'], ['text' => 'Урок 3']],
            [['text' => 'Урок 4'], ['text' => 'Урок 5'], ['text' => 'Урок 6']],
            [['text' => 'Урок 7'], ['text' => 'Урок 8'], ['text' => 'Урок 9']],
            [['text' => 'Бонуси 🎁'], ['text' => 'Книга 📕'], ['text' => 'Куратор ➡️']],
        ],
        'resize_keyboard' => true,
    ];
}

function get_curator_keyboard($user_id) {
    return [
        'inline_keyboard' => [[['text' => "Відповісти учню 📩 (ID: $user_id)", 'callback_data' => "reply_$user_id"]]],
    ];
}

function get_admin_keyboard() {
    return [
        'keyboard' => [
            [['text' => 'Генерувати посилання'], ['text' => 'Кількість користувачів'], ['text' => 'Список користувачів']],
            [['text' => 'Видалити користувача']],
            [['text' => 'Головне меню']],
        ],
        'resize_keyboard' => true,
    ];
}

// ================== ДОПОМІЖНЕ ==================
function is_access_valid($chat_id) {
    global $curator_id, $user_access_time, $access_time;
    if ($chat_id == $curator_id) return true;
    $start = $user_access_time[$chat_id] ?? 0;
    return $start && (time() - $start <= $access_time);
}

function generate_invite_code() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < 12; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// ================== ОБРОБКА ОНОВЛЕНЬ ==================
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['message'])) {
    $msg        = $update['message'];
    $chat_id    = $msg['chat']['id'];
    $from_id    = $msg['from']['id'];
    $text       = trim($msg['text'] ?? '');
    $message_id = $msg['message_id'] ?? 0;
    $username   = $msg['from']['username']   ?? null;
    $first_name = $msg['from']['first_name'] ?? '';
    $last_name  = $msg['from']['last_name']  ?? '';

    // Команда входу в адмін-панель
    if (in_array($text, ['/admin', '/адмін', '/panel'])) {
        if ($from_id != $curator_id) {
            send_message($chat_id, "⛔ У вас немає доступу до адмін-панелі.");
        } else {
            send_message($chat_id, "👑 Адмін-панель активовано", get_admin_keyboard());
        }
        exit;
    }

    // /newlink (для сумісності)
    if ($text === '/newlink') {
        if ($from_id != $curator_id) {
            send_message($chat_id, "⛔ Доступ заборонено");
            exit;
        }
        $code = generate_invite_code();
        $invite_codes[$code] = null;
        save_data();
        $link = "https://t.me/$bot_name?start=$code";
        send_message($chat_id, "🔗 Нове посилання:\n\n$link");
        exit;
    }

    // /start з кодом
    if (strpos($text, '/start') === 0) {
        $args = preg_split('/\s+/', $text, 2);
        $code = trim($args[1] ?? '');
        $code = preg_replace('/\s+/', '', $code);

        if (empty($code)) {
            send_message($chat_id, "👋 Вітаю!\n⛔ Вхід тільки за одноразовим посиланням від куратора.");
            exit;
        }

        if (!isset($invite_codes[$code]) || $invite_codes[$code] !== null) {
            send_message($chat_id, "⛔ Посилання недійсне або вже використано.\nОтриманий код: `$code`");
            exit;
        }

        $invite_codes[$code] = $chat_id;
        $user_access_time[$chat_id] = time();
        save_data();
        send_message($chat_id, "✅ Доступ активовано на 3 місяці!\nОбери розділ 👇", get_main_keyboard());
        exit;
    }

    if ($text === '/menu' || $text === '/help') {
        if (is_access_valid($chat_id)) {
            send_message($chat_id, "👇 Головне меню", get_main_keyboard());
        }
        exit;
    }

    if (!is_access_valid($chat_id)) {
        send_message($chat_id, "⛔ Твій доступ закінчився.\nЗвернись до куратора за новим посиланням 🔗");
        exit;
    }

    // ──────────────────────────────
    // Блок для куратора / адміна
    // ──────────────────────────────
    if ($chat_id == $curator_id) {

        // Кнопки адмін-меню
        if ($text == 'Генерувати посилання') {
            $code = generate_invite_code();
            $invite_codes[$code] = null;
            save_data();
            $link = "https://t.me/$bot_name?start=$code";
            send_message($chat_id, "🔗 Нове посилання:\n\n$link", get_admin_keyboard());
            exit;
        }

        if ($text == 'Кількість користувачів') {
            $count = count($user_access_time);
            send_message($chat_id, "📊 Кількість користувачів: $count", get_admin_keyboard());
            exit;
        }

        if ($text == 'Список користувачів') {
            $list = "Список користувачів:\n\n";
            if (empty($user_access_time)) {
                $list .= "Поки немає.";
            } else {
                foreach ($user_access_time as $uid => $stime) {
                    $days_left = round(($access_time - (time() - $stime)) / 86400);
                    $list .= "🆔 $uid | " . date('d.m.Y H:i', $stime) . " | ≈ $days_left днів\n";
                }
            }
            send_message($chat_id, $list, get_admin_keyboard());
            exit;
        }

        if ($text == 'Видалити користувача') {
            $user_states[$chat_id] = 'delete_user';
            save_data();
            send_message($chat_id, "Введіть ID користувача для видалення:", get_admin_keyboard());
            exit;
        }

        if ($text == 'Головне меню') {
            send_message($chat_id, "Повернення до головного меню", get_main_keyboard());
            exit;
        }

        // Режим видалення (після натискання кнопки)
        if (isset($user_states[$chat_id]) && $user_states[$chat_id] === 'delete_user') {
            $uid = (int) $text;
            if (isset($user_access_time[$uid])) {
                unset($user_access_time[$uid]);
                foreach ($invite_codes as $c => &$v) if ($v == $uid) $v = null;
                save_data();
                send_message($chat_id, "✅ Користувача $uid видалено.", get_admin_keyboard());
            } else {
                send_message($chat_id, "❌ Користувача $uid не знайдено.", get_admin_keyboard());
            }
            unset($user_states[$chat_id]);
            save_data();
            exit;
        }

        // Якщо нічого не підійшло — підказка
        send_message($chat_id, "Для адмін-панелі використовуйте /admin\nАбо натисніть кнопку.", get_admin_keyboard());
        exit;
    }

    // ──────────────────────────────
    // Звичайний режим учня
    // ──────────────────────────────

    if ($text == 'Куратор ➡️') {
        $user_states[$chat_id] = 'support';
        save_data();
        send_message($chat_id, "💬 Режим спілкування з куратором активовано.\nПиши — надішлю куратору.\nВийти — кнопкою меню.", get_main_keyboard());
        exit;
    }

    if (isset($user_states[$chat_id]) && $user_states[$chat_id] === 'support') {
        if (preg_match('/^Урок \d+$/', $text) || in_array($text, ['Бонуси 🎁', 'Книга 📕', 'Куратор ➡️'])) {
            unset($user_states[$chat_id]);
            save_data();
        } else {
            $un = $username ? "@$username" : "(немає)";
            $fn = trim("$first_name $last_name") ?: "Невідомо";
            $info = "📩 Від учня:\n👤 $fn  $un\n🆔 $chat_id";

            send_message($curator_id, $info);
            forward_message($curator_id, $chat_id, $message_id);
            send_message($curator_id, "Відповісти 👇", get_curator_keyboard($chat_id));

            send_message($chat_id, "✅ Надіслано куратору!");
            exit;
        }
    }

    // Відповідь куратора учневі
    if ($chat_id == $curator_id && isset($curator_reply_to[$curator_id])) {
        $target = $curator_reply_to[$curator_id];
        $low = mb_strtolower($text);

        if (in_array($low, ['/stop', 'завершити', 'стоп', 'вихід'])) {
            unset($curator_reply_to[$curator_id]);
            save_data();
            send_message($chat_id, "Режим відповіді вимкнено.", get_admin_keyboard());
            exit;
        }

        send_message($target, "💬 Від куратора:\n\n$text");
        send_message($chat_id, "✅ Надіслано. Продовжуй або /stop", get_curator_keyboard($target));
        exit;
    }

    // Вихід з support при виборі меню
    if (isset($user_states[$chat_id]) && $user_states[$chat_id] === 'support' &&
        (preg_match('/^Урок \d+$/', $text) || in_array($text, ['Бонуси 🎁', 'Книга 📕']))) {
        unset($user_states[$chat_id]);
        save_data();
    }

    // Звичайне меню учня
    if (preg_match('/^Урок \d+$/', $text)) {
        send_message($chat_id, "$text 🚀\nМатеріал уроку (скоро заповнимо)", get_main_keyboard());
    } elseif ($text == 'Бонуси 🎁') {
        send_message($chat_id, "🎁 Бонуси скоро з’являться!", get_main_keyboard());
    } elseif ($text == 'Книга 📕') {
        send_message($chat_id, "📖 Книга / посібник (скоро)", get_main_keyboard());
    } else {
        send_message($chat_id, "Обери пункт меню 👇", get_main_keyboard());
    }

    exit;
}

// Inline callback
if (isset($update['callback_query'])) {
    $call = $update['callback_query'];
    $call_id = $call['id'];
    $from_id = $call['from']['id'];
    $data    = $call['data'] ?? '';

    if (strpos($data, 'reply_') === 0) {
        if ($from_id != $curator_id) {
            answer_callback_query($call_id, "⛔ Доступ заборонено");
            exit;
        }
        $user_id = (int) substr($data, 6);
        $curator_reply_to[$curator_id] = $user_id;
        save_data();
        answer_callback_query($call_id, "✅ Режим відповіді активовано");
        send_message($curator_id,
            "<b>Пишеш учню (ID: $user_id)</b>\nНадсилай повідомлення.\nЗавершити: /stop",
            get_curator_keyboard($user_id),
            'HTML'
        );
        exit;
    }
}

// Пінг / перевірка
if (empty($input)) {
    http_response_code(200);
    echo "Бот працює 🚗";
}
