<?php

// ================== ЗАВАНТАЖЕННЯ .env ==================
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

$token = getenv('BOT_TOKEN');
if (!$token) {
    die("⚠️ Не знайдено BOT_TOKEN!");
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
            error_log("Помилка завантаження: " . $e->getMessage());
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
        error_log("Помилка збереження: " . $e->getMessage());
    }
}

load_data();

// ================== ФУНКЦІЇ API ==================
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
        CURLOPT_TIMEOUT         => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function forward_message($chat_id, $from_chat_id, $message_id) {
    global $token;
    $url = "https://api.telegram.org/bot$token/forwardMessage";
    $post = compact('chat_id', 'from_chat_id', 'message_id');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
    curl_close($ch);
}

function answer_callback_query($id, $text) {
    global $token;
    $url = "https://api.telegram.org/bot$token/answerCallbackQuery";
    $post = ['callback_query_id' => $id, 'text' => $text];
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
            [['text' => 'Бонуси 🎁'], ['text' => 'Книга 📕'], ['text' => 'Куратор ➡️']]
        ],
        'resize_keyboard' => true,
        'row_width' => 3
    ];
}

function get_curator_keyboard($user_id) {
    return [
        'inline_keyboard' => [
            [['text' => "Відповісти учню 📩 (ID: $user_id)", 'callback_data' => "reply_$user_id"]]
        ]
    ];
}

function get_admin_keyboard() {
    return [
        'keyboard' => [
            [['text' => 'Генерувати посилання'], ['text' => 'Кількість користувачів'], ['text' => 'Список користувачів']],
            [['text' => 'Видалити користувача']],
            [['text' => 'Головне меню']]
        ],
        'resize_keyboard' => true,
        'row_width' => 3
    ];
}

// ================== ДОПОМІЖНЕ ==================
function is_access_valid($chat_id) {
    global $curator_id, $user_access_time, $access_time;
    if ($chat_id == $curator_id) return true;
    $start = $user_access_time[$chat_id]['start'] ?? $user_access_time[$chat_id] ?? 0;
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

    // /newlink
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
        $code_raw = trim($args[1] ?? '');
        $code = preg_replace('/\s+/', '', $code_raw);
        $code_normalized = strtoupper($code);

        file_put_contents(__DIR__ . '/debug_start.log', date('Y-m-d H:i:s') . " | chat_id: $chat_id | raw: '$code_raw' | clean: '$code' | upper: '$code_normalized'\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/debug_start.log', "Current invite_codes: " . json_encode($invite_codes, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

        if (empty($code)) {
            send_message($chat_id, "👋 Вітаю!\n⛔ Вхід тільки за одноразовим посиланням від куратора.");
            exit;
        }

        $found = false;
        $original_code = null;
        foreach ($invite_codes as $key => $value) {
            if (strtoupper($key) === $code_normalized) {
                $found = true;
                $original_code = $key;
                break;
            }
        }

        if (!$found || $invite_codes[$original_code] !== null) {
            $status = $found ? ($invite_codes[$original_code] === null ? 'null' : 'використано (ID: ' . $invite_codes[$original_code] . ')') : 'не знайдено';
            $debug_info = "⛔ Посилання недійсне або вже використано.\n\n" .
                          "Отримано код: '$code'\n" .
                          "Нормалізований: '$code_normalized'\n" .
                          "Статус: $status\n" .
                          "Всього кодів: " . count($invite_codes) . "\n" .
                          "Список кодів: " . implode(", ", array_keys($invite_codes));
            send_message($chat_id, $debug_info);
            exit;
        }

        $invite_codes[$original_code] = $chat_id;
        $user_access_time[$chat_id] = [
            'start'     => time(),
            'first_name'=> $first_name,
            'last_name' => $last_name,
            'username'  => $username,
        ];
        save_data();

        file_put_contents(__DIR__ . '/debug_start.log', date('Y-m-d H:i:s') . " | УСПІХ: активовано '$original_code' для $chat_id\n", FILE_APPEND);

        send_message($chat_id, "✅ Доступ активовано на 3 місяці!\nОбери розділ 👇", get_main_keyboard());
        exit;
    }

    if ($text === '/menu' || $text === '/help') {
        if (is_access_valid($chat_id)) {
            send_message($chat_id, "👇 Головне меню", get_main_keyboard());
        }
        exit;
    }

    // Перевірка доступу
    if (!is_access_valid($chat_id)) {
        send_message($chat_id, "⛔ Твій доступ закінчився.\nЗвернись до куратора за новим посиланням 🔗");
        exit;
    }

    // КРИТИЧНИЙ БЛОК: Куратор відповідає учневі — ПЕРЕД УСІМА ІНШИМИ УМОВАМИ КУРАТОРА
    if ($chat_id == $curator_id && isset($curator_reply_to[$curator_id])) {
        $target = $curator_reply_to[$curator_id];
        $low = mb_strtolower($text);

        if (in_array($low, ['/stop', 'завершити', 'стоп', 'вихід'])) {
            unset($curator_reply_to[$curator_id]);
            save_data();
            send_message($chat_id, "✅ Режим відповіді вимкнено.", get_admin_keyboard());
            exit;
        }

        file_put_contents(__DIR__ . '/debug_reply.log', date('Y-m-d H:i:s') . " | Куратор → учню $target: $text\n", FILE_APPEND);
        $result = send_message($target, "💬 Повідомлення від куратора:\n\n$text");

        file_put_contents(__DIR__ . '/debug_reply.log', date('Y-m-d H:i:s') . " | Результат відправки: " . json_encode($result) . "\n\n", FILE_APPEND);

        if (isset($result['ok']) && !$result['ok']) {
            $err = $result['description'] ?? 'невідома помилка';
            send_message($chat_id, "❌ Не вдалося надіслати учневі $target!\nПомилка: $err", get_admin_keyboard());
        } else {
            send_message($chat_id, "✅ Надіслано. Пиши далі або /stop", get_curator_keyboard($target));
        }
        exit;  // ВИХІД — щоб не йшло далі в "Адмін панель"
    }

    // Блок куратора (адмін-меню, кнопки тощо)
    if ($chat_id == $curator_id) {

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
                $list .= "Поки немає користувачів.";
            } else {
                foreach ($user_access_time as $uid => $info) {
                    $start_time = $info['start'] ?? $info;
                    $days_left  = round(($access_time - (time() - $start_time)) / 86400);
                    $date       = date('d.m.Y H:i', $start_time);

                    $name = trim(($info['first_name'] ?? '') . ' ' . ($info['last_name'] ?? ''));
                    $un   = $info['username'] ?? null;
                    $display = $name ?: ($un ? "@$un" : "Без імені (ID $uid)");

                    $list .= "🆔 $uid\n   👤 $display\n   Початок: $date\n   Залишилось ≈ $days_left днів\n\n";
                }
            }
            send_message($chat_id, $list, get_admin_keyboard());
            exit;
        }

        if ($text == 'Видалити користувача') {
            $user_states[$chat_id] = 'delete_user';
            save_data();
            send_message($chat_id, "✏️ Введіть ID користувача для видалення:", get_admin_keyboard());
            exit;
        }

        // Видалення через команду
        if (preg_match('/^\/(delete|видалити)\s+(\d+)$/iu', $text, $m)) {
            $uid = (int)$m[2];
            if (isset($user_access_time[$uid])) {
                unset($user_access_time[$uid]);
                foreach ($invite_codes as $c => &$v) {
                    if ($v == $uid) $v = null;
                }
                save_data();
                send_message($chat_id, "✅ Користувача $uid видалено з підписки.", get_admin_keyboard());
            } else {
                send_message($chat_id, "❌ Користувача $uid не знайдено.", get_admin_keyboard());
            }
            exit;
        }

        // Режим видалення після кнопки "Видалити користувача"
        if (isset($user_states[$chat_id]) && $user_states[$chat_id] === 'delete_user') {
            $uid = (int) trim($text);  // видаляємо зайві пробіли
            if ($uid > 0 && isset($user_access_time[$uid])) {
                unset($user_access_time[$uid]);
                foreach ($invite_codes as $c => &$v) {
                    if ($v == $uid) $v = null;
                }
                save_data();
                send_message($chat_id, "✅ Користувача $uid видалено з підписки.", get_admin_keyboard());
            } else {
                send_message($chat_id, "❌ Користувача з ID $uid не знайдено.", get_admin_keyboard());
            }
            unset($user_states[$chat_id]);
            save_data();
            exit;
        }

        if ($text == 'Головне меню') {
            send_message($chat_id, "Повернення до головного меню", get_main_keyboard());
            exit;
        }

        // Підказка, якщо нічого не підійшло
        send_message($chat_id, "👇 Адмін панель", get_admin_keyboard());
        exit;
    }

    // Учень натискає "Куратор ➡️"
    if ($text == 'Куратор ➡️') {
        $user_states[$chat_id] = 'support';
        save_data();
        send_message($chat_id, "💬 Тепер ти в режимі спілкування з куратором.\nПиши повідомлення — вони будуть надіслані.\n\nЩоб вийти — натисни будь-яку кнопку знизу (Урок, Бонуси тощо)", get_main_keyboard());
        exit;
    }

    // Повідомлення від учня в режимі support
    if (isset($user_states[$chat_id]) && $user_states[$chat_id] === 'support' && $chat_id != $curator_id) {
        if (preg_match('/^Урок \d+$/', $text) || in_array($text, ['Бонуси 🎁', 'Книга 📕', 'Куратор ➡️'])) {
            unset($user_states[$chat_id]);
            save_data();
        } else {
            $username_str = $username ? "@$username" : "(немає username)";
            $full_name = trim("$first_name $last_name") ?: "Невідомо";
            $info_text = "📩 Повідомлення від учня:\n\n👤 $full_name\n$username_str\n🆔 ID: $chat_id";

            send_message($curator_id, $info_text);
            forward_message($curator_id, $chat_id, $message_id);
            send_message($curator_id, "📝 Натисни кнопку, щоб відповісти 👇", get_curator_keyboard($chat_id));

            send_message($chat_id, "✅ Повідомлення надіслано куратору!\nПиши далі або вийди в меню кнопкою знизу.");
            exit;
        }
    }

    // Вихід з support
    if (isset($user_states[$chat_id]) && $user_states[$chat_id] === 'support' &&
        (preg_match('/^Урок \d+$/', $text) || in_array($text, ['Бонуси 🎁', 'Книга 📕']))) {
        unset($user_states[$chat_id]);
        save_data();
    }

    // Звичайне меню учня
    if (preg_match('/^Урок \d+$/', $text)) {
        send_message($chat_id, "$text 🚀\n\nТут буде матеріал уроку...", get_main_keyboard());
    } elseif ($text == 'Бонуси 🎁') {
        send_message($chat_id, "🎁 Бонуси та додаткові матеріали...\nСкоро тут з'явиться контент!", get_main_keyboard());
    } elseif ($text == 'Книга 📕') {
        send_message($chat_id, "📖 Посібник з ПДР та навчання...\nСкоро додамо!", get_main_keyboard());
    } else {
        send_message($chat_id, "👇 Обери пункт з меню", get_main_keyboard());
    }
    exit;
}

// ================== CALLBACK ==================
if (isset($update['callback_query'])) {
    $call = $update['callback_query'];
    $call_id = $call['id'];
    $from_id = $call['from']['id'];
    $data = $call['data'] ?? '';

    if (strpos($data, 'reply_') === 0) {
        if ($from_id != $curator_id) {
            answer_callback_query($call_id, "⛔ Доступ заборонено");
            exit;
        }

        $user_id = (int) substr($data, 6);
        $curator_reply_to[$curator_id] = $user_id;
        save_data();

        file_put_contents(__DIR__ . '/debug_reply.log', date('Y-m-d H:i:s') . " | Активовано режим для учня $user_id\n", FILE_APPEND);

        answer_callback_query($call_id, "✅ Активовано відповідь учню");
        send_message($curator_id,
            "<b>Ти пишеш учню (ID: $user_id)</b>\n\nНадсилай повідомлення — вони підуть йому.\n<i>Кнопка завжди активна. Завершити: /stop</i>",
            get_curator_keyboard($user_id),
            'HTML'
        );
        exit;
    }
}

// Пінг
if (empty($input)) {
    echo "Бот автошколи працює! 🚀";
}
