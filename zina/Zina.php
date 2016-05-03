<?php

//use BotDao;
//use Util;
//use Request;

class Zina
{

    private $db;

    /**
     * Axenia constructor.
     * @param $db BotDao
     */
    public function __construct($db)
    {
        $this->db = $db;
    }


    public function processMessage($message)
    {
        $message_id = $message['message_id'];
        $chat = $message['chat'];
        $chat_id = $chat['id'];
        $from = $message['from'];
        $from_id = $from['id'];

        $this->db->AddUser($from_id, $from['username'], $from['first_name'], $from['last_name']);

        $lang = $this->db->getLang($chat_id, $chat['type']);

        if ($lang === false) {
            $lang = 'en';
        }
        Lang::init($lang);

        if (isset($message['text'])) {
            $text = str_replace("@" . BOT_NAME, "", $message['text']);
            switch (true) {
                case preg_match('/^\/lang/ui', $text, $matches):
                    $array = array_values(Lang::$availableLangs);
                    $replyKeyboardMarkup = array("keyboard" => array($array), "selective" => true, "one_time_keyboard" => true);
                    $text = Lang::message('chat.lang.start', array("langs" => Util::arrayInColumn($array)));
                    Request::sendMessage($chat_id, array("text" => $text, "reply_to_message_id" => $message_id, "reply_markup" => $replyKeyboardMarkup));
                    break;
                case (($pos = array_search($text, Lang::$availableLangs)) !== false):
                    $qrez = $this->db->setLang($chat_id, $chat['type'], $pos);
                    $replyKeyboardHide = array("hide_keyboard" => true, "selective" => true);
                    $text = Lang::message('bot.error');
                    if ($qrez) {
                        Lang::init($pos);
                        $text = Lang::message('chat.lang.end');
                    }
                    Request::sendMessage($chat_id, array("text" => $text, "reply_to_message_id" => $message_id, "reply_markup" => $replyKeyboardHide));
                    break;
                
                case (preg_match('/^\/start/ui', $text, $matches) and $chat['type'] == "private"):
                    Request::sendTyping($chat_id);
                    Request::sendHtmlMessage($chat_id, Lang::message('chat.greetings2'));
                    sleep(1);
                    Request::sendHtmlMessage($chat_id, Lang::message('user.pickChat', array('botName' => BOT_NAME)));
                    break;

                case preg_match('/^\/top/ui', $text, $matches):
                case preg_match('/^\/Stats/ui', $text, $matches):
                    Request::sendTyping($chat_id);

                    $out = Lang::message('karma.top.title2', array("chatName" => $this->db->GetGroupName($chat_id)));
                    $top = $this->db->getTop($chat_id, 5);
                    $a = array_chunk($top, 4);
                    foreach ($a as $value) {
                        $username = ($value[0] == "") ? $value[1] . " " . $value[2] : $value[0];
                        $out .= Lang::message('karma.top.row2', array("username" => $username, "karma" => $value[3]));
                    }
                    $out .= Lang::message('karma.top.footer', array("pathToSite" => PATH_TO_SITE, "chatId" => $chat_id));

                    Request::sendHtmlMessage($chat_id, $out);
                    break;

                case preg_match('/^(\+|\-|👍|👎) ?([\s\S]+)?/ui', $text, $matches):
                    $dist = Util::isInEnum("+,👍", $matches[1]) ? "+" : "-";

                    if (isset($message['reply_to_message'])) {
                        $replyUser = $message['reply_to_message']['from'];
                        $this->db->AddUser($replyUser['id'], $replyUser['username'], $replyUser['first_name'], $replyUser['last_name']);

                        if ($replyUser['username'] != BOT_NAME) {
                            Request::sendTyping($chat_id);
                            $output = $this->db->HandleKarma($dist, $from_id, $replyUser['id'], $chat_id);
                            Request::sendHtmlMessage($chat_id, $output);
                        }
                    } else {
                        if (preg_match('/@([\w]+)/ui', $matches[2], $user)) {
                            $to = $this->db->GetUserID($user[1]);
                            if ($to) {
                                Request::sendHtmlMessage($chat_id, $this->db->HandleKarma($dist, $from_id, $to, $chat_id));
                            } else {
                                Request::sendHtmlMessage($chat_id, Lang::message('karma.unknownUser'), array('reply_to_message_id' => $message_id));
                            }
                        }

                    }
                    break;
            }
        }

        if (isset($message['new_chat_member'])) {
            $newMember = $message['new_chat_member'];
            if (BOT_NAME == $newMember['username']) {
                $chat = $message['chat'];
                $output = $this->db->AddChat($chat_id, $chat['title'], $chat['type']);
                if ($output !== false) {
                    Request::sendTyping($chat_id);
                    Request::exec("sendMessage", array('chat_id' => $chat_id, "text" => $output, "parse_mode" => "Markdown"));
                }
            } else {
                $this->db->AddUser($newMember['id'], $newMember['username'], $newMember['first_name'], $newMember['last_name']);
            }
        }

        if (isset($message['new_chat_title'])) {
            $newtitle = $message['new_chat_title'];
            $this->db->AddChat($chat_id, $newtitle, $chat = $message['chat']['type']);
        }

        if (isset($message['sticker'])) {
            //обработка получения стикеров
        }

        if (isset($message['left_chat_member'])) {
            //не видит себя когда его удаляют из чата
            $member = $message['left_chat_member'];
            if (BOT_NAME == $member['username']) $this->db->DeleteChat($chat_id);
        }
    }

}

?>