<?php

// ================== НАСТРОЙКИ ==================
$token = getenv("BOT_TOKEN");
if (!$token) {
    die("⚠️ Не знайдено BOT_TOKEN!");
}

$bot_name = "DriverAutoSchool_bot";
$curator_id = 761584410;
$access_time = 90 * 24 * 60 * 60;  // 90 днів

// ================== ДАНІ ==================
$data_file = "bot_data.json";
$invite_codes = [];
$user_access_time = [];
$user_states = [];
$curator_reply_to = [];

function load_data()
{
    global $data_file, $invite_codes, $user_access_time, $user_states, $curator_reply_to;
    if (file_exists($data_file)) {
        try {
            $data = json_decode(file_get_contents($data_file), true);
            $invite_codes = $data['invite_codes'] ?? [];
            $user_access_time = $data['user_access_time'] ?? [];
            $user_states = $data['user_states'] ?? [];
            $curator_reply_to = $data['curator_reply_to'] ?? [];
            echo "✅ Дані завантажено\n";
        } catch (Exception $e) {
            echo "⚠️ Помилка завантаження: " . $e->getMessage() . "\n";
        }
    }
}

function save_data()
{
    global $data_file, $invite_codes, $user_access_time, $user_states, $curator_reply_to;
    try {
        $data = [
            "invite_codes" => $invite_codes,
            "user_access_time" => $user_access_time,
            "user_states" => $user_states,
            "curator_reply_to" => $curator_reply_to
        ];
        file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    } catch (Exception $e) {
        echo "⚠️ Помилка збереження: " . $e->getMessage() . "\n";
    }
}

load_data();

// ================== ФУНКЦІЇ ДЛЯ API ==================
function send_message($chat_id, $text, $reply_markup = null, $parse_mode = null)
{
    global $token;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    if ($reply_markup) {
        $post_fields['reply_markup'] = json_encode($reply_markup);
    }
    if ($parse_mode) {
        $post_fields['parse_mode'] = $parse_mode;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function forward_message($chat_id, $from_chat_id, $message_id)
{
    global $token;
    $url = "https://api.telegram.org/bot$token/forwardMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function answer_callback_query($callback_query_id, $text)
{
    global $token;
    $url = "https://api.telegram.org/bot$token/answerCallbackQuery";
    $post_fields = [
        'callback_query_id' => $callback_query_id,
        'text' => $text
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// ================== КЛАВІАТУРИ ==================
function get_main_keyboard()
{
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

function get_curator_keyboard($user_id)
{
    return [
        'inline_keyboard' => [
            [['text' => "Відповісти учню 📩 (ID: $user_id)", 'callback_data' => "reply_$user_id"]]
        ]
    ];
}

function get_admin_keyboard()
{
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
function is_access_valid($chat_id)
{
    global $curator_id, $user_access_time, $access_time;
    if ($chat_id == $curator_id) {
        return true;
    }
    $start_time = $user_access_time[$chat_id] ?? 0;
    return $start_time && (time() - $start_time <= $access_time);
}

function generate_invite_code()
{
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
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $from_id = $message['from']['id'];
    $message_id = $message['message_id'];
    $username = $message['from']['username'] ?? null;
    $first_name = $message['from']['first_name'] ?? '';
    $last_name = $message['from']['last_name'] ?? '';

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

    if (strpos($text, '/start') === 0) {
        $args = preg_split('/\s+/', $text, 2);
        if (count($args) < 2 || empty(trim($args[1]))) {
            send_message($chat_id, "👋 Вітаю!\n⛔ Вхід тільки за одноразовим посиланням від куратора.");
            exit;
        }
        $code = trim($args[1]);
        if (!isset($invite_codes[$code]) || $invite_codes[$code] !== null) {
            send_message($chat_id, "⛔ Посилання недійсне або вже використано");
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

    // Загальна обробка повідомлень
    if (!is_access_valid($chat_id)) {
        send_message($chat_id, "⛔ Твій доступ закінчився.\nЗвернись до куратора за новим посиланням 🔗");
        exit;
    }

    // Обробка для куратора (адміна)
    if ($chat_id == $curator_id) {
        if ($text == 'Генерувати посилання') {
            $code = generate_invite_code();
            $invite_codes[$code] = null;
            save_data();
            $link = "https://t.me/$bot_name?start=$code";
            send_message($chat_id, "🔗 Нове посилання:\n\n$link");
            exit;
        } elseif ($text == 'Кількість користувачів') {
            $count = count($user_access_time);
            send_message($chat_id, "📊 Кількість користувачів: $count");
            exit;
        } elseif ($text == 'Список користувачів') {
            $list = "";
            foreach ($user_access_time as $uid => $stime) {
                $list .= "🆔 ID: $uid, Початок доступу: " . date('Y-m-d H:i:s', $stime) . "\n";
            }
            send_message($chat_id, $list ?: "Немає користувачів");
            exit;
        } elseif ($text == 'Видалити користувача') {
            $user_states[$chat_id] = 'delete_user';
            save_data();
            send_message($chat_id, "✏️ Введіть ID користувача для видалення:");
            exit;
        } elseif ($text == 'Головне меню') {
            send_message($chat_id, "👇 Головне меню", get_main_keyboard());
            exit;
        } elseif (isset($user_states[$chat_id]) && $user_states[$chat_id] == 'delete_user') {
            $uid = (int) $text;
            if (isset($user_access_time[$uid])) {
                unset($user_access_time[$uid]);
                foreach ($invite_codes as $code => $id) {
                    if ($id == $uid) {
                        $invite_codes[$code] = null;
                    }
                }
                save_data();
                send_message($chat_id, "✅ Користувача $uid видалено.");
            } else {
                send_message($chat_id, "❌ Користувача не знайдено.");
            }
            unset($user_states[$chat_id]);
            save_data();
            exit;
        } else {
            // Показати адмін меню за замовчуванням для куратора
            send_message($chat_id, "👇 Адмін панель", get_admin_keyboard());
            exit;
        }
    }

    // Учень натискає "Куратор ➡️"
    if ($text == 'Куратор ➡️') {
        $user_states[$chat_id] = 'support';
        save_data();
        send_message($chat_id, "💬 Тепер ти в режимі спілкування з куратором.\nПиши повідомлення — вони будуть надіслані.\n\nЩоб вийти в меню — просто натисни будь-яку кнопку знизу (Урок, Бонуси тощо)", get_main_keyboard());
        exit;
    }

    // Учень в режимі підтримки
    if (isset($user_states[$chat_id]) && $user_states[$chat_id] == 'support' && $chat_id != $curator_id) {
        if (strpos($text, 'Урок ') === 0 || in_array($text, ['Бонуси 🎁', 'Книга 📕', 'Куратор ➡️'])) {
            unset($user_states[$chat_id]);
            save_data();
            // Продовжити обробку як меню
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

    // Куратор відповідає
    if ($chat_id == $curator_id && isset($curator_reply_to[$curator_id])) {
        $user_id = $curator_reply_to[$curator_id];
        $lower_text = mb_strtolower($text);
        if (in_array($lower_text, ['/stop', 'завершити', 'стоп', 'вихід'])) {
            unset($curator_reply_to[$curator_id]);
            save_data();
            send_message($curator_id, "✅ Режим відповіді вимкнено.");
            exit;
        }
        send_message($user_id, "💬 Повідомлення від куратора:\n\n$text");
        send_message($curator_id, "✅ Надіслано. Пиши далі або /stop для завершення.", get_curator_keyboard($user_id));
        exit;
    }

    // Вихід з режиму підтримки
    if (isset($user_states[$chat_id]) && $user_states[$chat_id] == 'support' && (strpos($text, 'Урок ') === 0 || in_array($text, ['Бонуси 🎁', 'Книга 📕']))) {
        unset($user_states[$chat_id]);
        save_data();
    }

    // Звичайна обробка меню
    if (strpos($text, 'Урок ') === 0) {
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

if (isset($update['callback_query'])) {
    $call = $update['callback_query'];
    $call_id = $call['id'];
    $from_id = $call['from']['id'];
    $data = $call['data'];

    if (strpos($data, 'reply_') === 0) {
        if ($from_id != $curator_id) {
            answer_callback_query($call_id, "⛔ Доступ заборонено");
            exit;
        }
        $user_id = (int) explode('_', $data)[1];
        $curator_reply_to[$curator_id] = $user_id;
        save_data();
        answer_callback_query($call_id, "✅ Активовано відповідь учню");
        send_message($curator_id, "<b>Ти пишеш учню (ID: $user_id)</b>\n\nНадсилай повідомлення — вони підуть йому.\n<i>Кнопка завжди активна. Завершити: /stop</i>", get_curator_keyboard($user_id), 'HTML');
        exit;
    }
}

// ================== WEBHOOK SETUP ==================
// Для налаштування webhook викличте цей скрипт з параметром ?set_webhook=1 (тільки для адміна)
if (isset($_GET['set_webhook'])) {
    $webhook_url = getenv("WEBHOOK_URL");
    if ($webhook_url) {
        $url = "https://api.telegram.org/bot$token/setWebhook?url=$webhook_url";
        $result = file_get_contents($url);
        echo $result;
    } else {
        echo "⚠️ WEBHOOK_URL не задано";
    }
    exit;
}

// Якщо немає input — це може бути запит на корінь
if (empty($input)) {
    echo "Бот автошколи працює! 🚀";
}