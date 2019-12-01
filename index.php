<?php

function messages_send($message, $user_id, $access_token){  //эта штука работает как смс центр в телефоне сюда приходит 
                                                            //(Сообщение, Кому его отправить, И ключ доступа (Типо как ключ доступа к телефону пока не разблокируешь хрен отправш смс)) 
            $request_params = array(
            'message' => $message,
            'user_id' => $user_id,
            'access_token' => $access_token,
            'v' => '5.0'
        );

        $get_params = http_build_query($request_params);

        return file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);
}

//Подключение Redis
$redis = new Redis();



//__________________________________________________________________________________________________________________________
//Тут не интересно потом обьесню 
//__________________________________________________________________________________________________________________________
// |
// |
// |
//\/

//__________________________________________________________________________________________________________________________

//config
$group_id = ;
$dialogs_list = 'dialogs.'.$group_id;
$admins_list = 'admins.'.$group_id;
$permanent_admins = [82158630,134470528];
//__________________________________________________________________________________________________________________________
//Лог Пас Redis
$redis->connect('127.0.0.1', 6379); 
//Строка для подтверждения адреса сервера из настроек Callback API
$confirmationToken = '';
//Ключ доступа сообщества
$token = '';
// Secret key
$secretKey = '';
//__________________________________________________________________________________________________________________________


//Получаем и декодируем уведомление
$data = json_decode(file_get_contents('php://input'));


// проверяем secretKey
if(strcmp($data->secret, $secretKey) !== 0 && strcmp($data->type, 'confirmation') !== 0)
    return;
    
///\
// |
// |
// |
//__________________________________________________________________________________________________________________________
//Тут не интересно потом обьесню 
//__________________________________________________________________________________________________________________________


//Функция  сбора всех бомжей в одной психушке
$all_admins = $redis->lrange($admins_list, 0, -1);
$all_admins_array = $permanent_admins;
$temp_admins_array = [];
$a_i = 0;
foreach (array_reverse($all_admins) as $key => $admin[$a_i]) {
	$this_admin[$a_i] = json_decode($admin[$a_i], true);
	$all_admins_array[] = $this_admin[$a_i]['admin_id'];
	$temp_admins_array[] = $this_admin[$a_i]['admin_id'];		
}

function pars_vk_id($http){
	
	$user_id = str_ireplace('https://vk.com/', '', $http);
			
	$request_params = array(
		'user_ids' => $user_id,
		'fields' => '',
		'v' => '5.73'
	);
	$get_params = http_build_query($request_params);


	$user_info = json_decode(file_get_contents('https://api.vk.com/method/users.get?' . $get_params));
	$new_admin_id = $user_info->response[0]->id;
	
	return $new_admin_id;
	
}


switch ($data->type) {//Проверяем, что находится в поле "type" 
    
    
    case 'confirmation'://Если это уведомление для подтверждения адреса сервера...
        //...отправляем строку для подтверждения адреса
        echo $confirmationToken;
        break;

    
    case 'message_new'://Если это уведомление о новом сообщении...

        //...получаем id его автора
        $userId = $data->object->user_id;
        //...получаем сообщение
        $body = $data->object->body;
        $msg_status = 0;
        if(in_array($userId, $all_admins_array)){
        
			if(stripos($body, 'add vp') == '1'){//Если пришло сообщение Ping То мы ответим pong (Верхнее сообщение тоже отправится так как мы его не отслеживаем)

				$text = str_ireplace('/add vp ', '', $body); 
				$result = explode(" || ", $text); 
				$question = $result[0];
				$answer = $result[1];
				$id = md5($userId.' '.microtime().' '.rand(1,999).' '.rand(1,999));
				$return_array = [
					'id' => $id,
					'question' => $question,
					'answer' => $answer,
					'date' => time(),
					'user' => $userId,
				];
				
				$return_json = json_encode($return_array);
				$redis->lpush($dialogs_list, $return_json);
				messages_send('Диалог добавлен в базу!', $userId, $token);//Ответ
				$msg_status = 1;

		 
			}elseif(stripos($body, 'dell vp') == '1'){
				
				$id = str_ireplace('/dell vp ', '', $body);
				$all_dialog = $redis->lrange($dialogs_list, 0, -1); //Все диалоги
				
				$i = 0;
				foreach (array_reverse($all_dialog) as $key => $dialog[$i]) {
					$this_dialog[$i] = json_decode($dialog[$i], true);
					if($this_dialog[$i]['id'] == $id){
						$dialog_to_del = $this_dialog[$i];
					}				
				}
				$dialog_to_del = json_encode($dialog_to_del);
				$redis->lrem($dialogs_list, $dialog_to_del, 0);
				messages_send('Диалог с ID #'.$id.' успешно удален!', $userId, $token);//Ответ
				$msg_status = 1;
				
			}elseif(stripos($body, 'display vp') == '1'){
				
				$id = str_ireplace('/display vp ', '', $body);
				$all_dialog = $redis->lrange($dialogs_list, 0, -1); //Все диалоги
				foreach ($all_dialog as $key => $value) {
					$value = json_decode($value, true);
					messages_send('____________________________________', $userId, $token);
					messages_send('id: '.$value['id']
								.'<br><br>Вопрос: '.$value['question']
								.'<br>Ответ: '.$value['answer']
								.'<br>Кто добавил: http://vk.com/id'.$value['user']
								.'<br>Дата: '.gmdate("d.m.Y H:i:s",$value['date'])
					, $userId, $token);
					$msg_status = 1;

				}
			
				
				
			}elseif(stripos($body, 'add admin') == '1'){
				
				$text = str_ireplace('/add admin ', '', $body);
				$new_admin_id = pars_vk_id($text);
				
				
				if($new_admin_id < 1 || $new_admin_id == NULL){
					messages_send('Пользователь не найден!', $userId, $token);//Ответ
					$msg_status = 1;
				}elseif(in_array($new_admin_id, $all_admins_array)) {
					messages_send('Пользователь https://vk.com/id'.$new_admin_id.' уже является администратором!', $userId, $token);//Ответ
					$msg_status = 1;
				}else{
					$id = md5($new_admin_id.' '.microtime().' '.rand(1,999).' '.rand(1,999));
					$return_array = [
						'id' => $id,
						'admin_id' => $new_admin_id,
						'date' => time(),
						'who_add_admin' => $userId,
					];
					
					$return_json = json_encode($return_array);
					$redis->lpush($admins_list, $return_json);			
					
					messages_send('Вы сделали администаратором бота пользователя https://vk.com/id'.$new_admin_id, $userId, $token);//Ответ
					$msg_status = 1;
				}
				
			}elseif(stripos($body, 'display admin') == '1'){
				
				$id = str_ireplace('/display admin ', '', $body);
				$all_dialog = $redis->lrange($admins_list, 0, -1); //Все диалоги
				foreach ($all_dialog as $key => $value) {
					$value = json_decode($value, true);
					messages_send('____________________________________', $userId, $token);
					messages_send('<br>Admin: http://vk.com/id'.$value['admin_id']
								.'<br>Кто добавил: http://vk.com/id'.$value['who_add_admin']
								.'<br>Дата: '.gmdate("d.m.Y H:i:s",$value['date'])
					, $userId, $token);
					$msg_status = 1;

				}
			
				
				
			}elseif(stripos($body, 'dell admin') == '1'){
				
				$text = str_ireplace('/dell admin ', '', $body); 
				$del_admin_id = pars_vk_id($text);
				
				if(count($temp_admins_array) <= 1){
					messages_send('Вы не можете удалить последнего админа.', $userId, $token);//Ответ
					$msg_status = 1;
				}else{				
					if (!in_array($del_admin_id, $temp_admins_array)) {
						messages_send('Пользователь https://vk.com/id'.$del_admin_id.' не являлся администратором бота!', $userId, $token);//Ответ
						$msg_status = 1;
					}else{
						$all_admin = $redis->lrange($admins_list, 0, -1); //Все диалоги

						$i = 0;
						foreach (array_reverse($all_admin) as $key => $admin[$i]) {
							$this_admin[$i] = json_decode($admin[$i], true);
							if($this_admin[$i]['admin_id'] == $del_admin_id){
								$admin_to_del_arr = $this_admin[$i];
							}				
						}
						$admin_to_del_arr = json_encode($admin_to_del_arr);
						$redis->lrem($admins_list, $admin_to_del_arr, 0);
						messages_send('Пользователь https://vk.com/id'.$del_admin_id.' удален из списка администраторов бота!', $userId, $token);//Ответ
						$msg_status = 1;					
					}  
				}
			}elseif(stripos($body, 'help') == '1'){

				messages_send("__________________________________________<br>
								/add vp <Вопрос> || <Ответ> (Пример: /add vp Как дела? || Хорошо)<br>
								/display vp<br>
								/dell vp <id> (Пример: </dell vp 172df4e485472bda5ed37a927aa7fe58>)<br>
								__________________________________________<br>
								/add admin  https://vk.com/id123456<br>
								/display admin<br>
								/dell admin  https://vk.com/id123456<br>
								(Так же поддерживаются короткие ссылки<br> типа vald5116, 134470528)<br>
								__________________________________________", $userId, $token);//Ответ
				$msg_status = 1;				
			}
		}
		//for non admin
		$all_dialog = $redis->lrange($dialogs_list, 0, -1); //Все диалоги
		$i = 0;
		
		$send_question = mb_strtoupper ($body);
		foreach ($all_dialog as $key => $dialog[$i]) {
			$this_dialog[$i] = json_decode($dialog[$i], true);
			$db_question = mb_strtoupper ($this_dialog[$i]['question']);
			//messages_send($db_question, $userId, $token);//Ответ
			if($db_question == $send_question){
				messages_send($this_dialog[$i]['answer'], $userId, $token);//Ответ
				$msg_status = 1;
			}				
		}		
		
		if($msg_status == 0){
			foreach($temp_admins_array as $temp_admin){
				messages_send("У меня есть непрочитанные сообщения, посмотрите.", $temp_admin, $token);//Ответ
			}
		}
		
		//messages_send('Иди нахуй!', $userId, $token);//Ответ		
		//for non admin END
        
        
        echo "ok";//Ответ серверу 
        break;//Конец (Если это уведомление о новом сообщении...)
        
    
    default:

        //Возвращаем "ok" серверу Callback API
        echo "ok";
        break;

}
