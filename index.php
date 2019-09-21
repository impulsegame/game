<?php

require_once("config.php");
require_once("main.php");

if($ssl==1 && empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off"){
header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
exit;
}


// ______________________ ЗАПИСЬ ДАННЫХ АВТОРИЗАЦИИ, ЗАОДНО ОБНОВЛЯЕМ, ЧТОБЫ НЕ ВЫКИНУЛО ____________________


if($u_id>0 && !empty($mydm['uid']) && (empty($_SESSION['nat']) || $_SESSION['nat']<$time-3600)){
$_SESSION['nat']=$time;
$aq=mysqli_query($db,"SELECT id FROM auth ORDER BY id DESC LIMIT 1") or die ('cant id auth');
$am=mysqli_fetch_row($aq);
if(!empty($am[0])){
mysqli_query($db,"DELETE FROM auth WHERE id<".($am[0]-10));
mysqli_query($db,"OPTIMIZE TABLE auth") or die('cant optimize auth');
}
$browser=$_SERVER['HTTP_USER_AGENT'];
$browser=substr($browser,0,200);
$browser=preg_replace('#[^a-z0-9\/\ \.\,\;\(\)]+#i','',$browser);
mysqli_query($db,"INSERT INTO auth (date,date_word,uid,login,avatar,ip,browser) VALUES ('$time','".date('d.m.y H:i:s',$time)."','".$mydm['uid']."','".$mydm['login']."','".$mydm['avatar']."','$ip','$browser')");
mysqli_query($db,"UPDATE users SET date2='$time',date2_word='".date('d.m.y H:i:s',$time)."',ipcame='$ip' WHERE uid='$u_id' LIMIT 1") or die('cant auth users date2');
mysqli_query($db,"UPDATE room_status SET auth='$time' LIMIT 1") or die('cant auth room_status');
}


// ______________________ ПЕРЕКЛЮЧЕНИЕ НА МОБИЛЬНУЮ ВЕРСИЮ ____________________


if(isset($_GET['mobile']) && ($_GET['mobile']==0 || $_GET['mobile']==1)){
$_SESSION['mobile']=$_GET['mobile'];
$mobile=$_GET['mobile'];
}


$page='';
if(!empty($_GET['page'])){
$page=preg_replace("#[^a-z\_\-0-9]+#i",'',$_GET['page']);
}


// ______________________ ПЕРЕХОД РЕФЕРАЛА ____________________


if(!empty($_GET['ref']) && empty($_SESSION['ref_id']) && empty($_SESSION['uid'])){
session_unset();
$_GET['ref']=preg_replace("#[^0-9]+#i",'',$_GET['ref']);
if(!empty($_GET['ref']) && strlen($_GET['ref'])<7){
$ref_q=mysqli_query($db,"SELECT uid FROM users WHERE uid='".$_GET['ref']."' LIMIT 1") or die('cant set referer');
$ref_m=mysqli_fetch_row($ref_q);
if(!empty($ref_m[0])){
$_SESSION['ref_id']=$ref_m[0];
}
}
}


// ______________________ ОТКУДА ПРИШЁЛ ____________________


if(empty($_SESSION['uid']) && !isset($_SESSION['urlfrom']) && !empty($_SERVER['HTTP_REFERER'])){
$urlfrom=preg_replace('#[^a-z0-9\.\?\/\:\=\-\_\&]+#i','',$_SERVER['HTTP_REFERER']);
$urlfrom=substr($urlfrom,0,200);
$_SESSION['urlfrom']=$urlfrom;
}


// ______________________ ДАННЫЕ ____________________


$datas=mysqli_query($db,"SELECT * FROM data LIMIT 1") or die('cant select data');
$d=mysqli_fetch_assoc($datas);
if(!isset($d['users'])){ exit('data error'); }


// ______________________ ОНЛАЙН - ОБНОВЛЕНИЕ ____________________


if(!empty($mydm['uid'])){
mysqli_query($db,"UPDATE users SET date2='$time' WHERE uid='$u_id' LIMIT 1") or die('cant update date2');
$ioq=mysqli_query($db,"SELECT id FROM online WHERE uid='".$mydm['uid']."' LIMIT 1") or die ('cant online 1');
$iom=mysqli_fetch_row($ioq);
$ion=1;
if(empty($iom[0])){
mysqli_query($db,"INSERT INTO online (ip,last_time,uid,login,avatar) VALUES ('$ip','".($time+$on_time*60)."','".$mydm['uid']."','".$mydm['login']."','".$mydm['avatar']."')") or die ('cant online 2');
$ion=0;
}
if($ion==1){
mysqli_query($db,"UPDATE online SET last_time='".($time+$on_time*60)."' WHERE uid='".$mydm['uid']."' LIMIT 1") or die ('cant online 3');
}
}


// ______________________ ОНЛАЙН - СПИСОК ____________________


$who_online='';
$who_td=0;
$who_count=0;


$who_q=mysqli_query($db,"SELECT uid,login,avatar FROM online");
while($who_m=mysqli_fetch_row($who_q)){
$who_count++;
if($who_td==0){ $who_online.='<tr>'; }
$p_url=md5($who_m[0].$p_hash);
$p_url=$who_m[0].'_'.substr($p_url,0,4);
$who_online.='<td class="head_who_online_td"><a target="_blank" href="/profile/'.$p_url.'"><div><img src="'.$who_m[2].'"><span>'.$who_m[1].'</span></div></a></td>';
$who_td++;
if($who_td>3){ $who_td=0; $who_online.='</tr>'; }
}


if($who_count==0){ $who_online='<tr><td colspan="3">Онлайн никого нет</td></tr>'; }


// ______________________ ОНЛАЙН - УДАЛЕНИЕ ____________________


if($d['online_dt']<$time){
mysqli_query($db,"DELETE FROM online WHERE last_time<$time");
mysqli_query($db,"OPTIMIZE TABLE online") or die('cant optimize online');
mysqli_query($db,"UPDATE data SET online_dt='".($time+$on_time*60)."' LIMIT 1") or die('cant update online_dt');
}


// ______________________ ПЕРЕВОД НА F КОШЕЛЁК ____________________


if($d['fk_b']>=50 && $d['fk_time']<$time && $fk_mode==1){
mysqli_query($db,"UPDATE data SET fk_time='".($time+60)."' LIMIT 1") or die('cant data');
$fk_send=@file_get_contents('http://www.free-kassa.ru/api.php?merchant_id='.$fk_id.'&s='.md5($fk_id.$fk_secret_2).'&action=payment&currency=fkw&amount='.$d['fk_b']);
$fk_send=iconv('utf-8','cp1251',$fk_send);
preg_match('#Заявка\ отправлена#',$fk_send,$fk_send_m);
if(!empty($fk_send_m)){
mysqli_query($db,"UPDATE data SET fk_b=0 LIMIT 1") or die('cant data');
}
//else{ echo $fk_send; }
}


// ______________________ ЕЖЕДНЕВНАЯ ЗАДАЧА ____________________


$now_d=date('j',$time);


//___________________________________ ПРОСМОТРЫ ВЧЕРА, СЕГОДНЯ, ВСЕГО _________________________________________________


if($now_d==$d['day']){
mysqli_query($db,"UPDATE data SET v_n=v_n+1,v_a=v_a+1 LIMIT 1") or die('cant update views');
}


if($now_d!=$d['day']){
// СТИРАЕМ СТАРЫЕ ЗАПИСИ REQUEST
mysqli_query($db,'DELETE FROM request WHERE rdate1<'.($time-86400*$h_time));
// СТИРАЕМ СТАРЫЕ ЗАПИСИ РЕФЕРАЛЬНЫХ НАЧИСЛЕНИЙ
mysqli_query($db,'DELETE FROM refs_profit WHERE date<'.($time-86400*$rp_time));
// ОБНУЛЯЕМ КОЛИЧЕСТВО НОВЫХ ЗАРЕГИСТРИРОВАННЫХ УЧАСТНИКОВ
mysqli_query($db,"UPDATE data SET users_today=0,day=$now_d,v_y=v_n,v_n=1,v_a=v_a+1 LIMIT 1") or die('cant update');
// СТИРАЕМ СТАРЫЕ ВЫИГРЫШИ ОБЫЧНОЙ КОМНАТЫ
$rw_a=array();
$rw_q=mysqli_query($db,"SELECT num,id FROM room_winners ORDER BY id DESC") or die ('cant select room_winners');
while($rw_m=mysqli_fetch_row($rw_q)){
if(!isset($rw_a[$rw_m[0]])){ $rw_a[$rw_m[0]]=array(0,0); }
if($rw_a[$rw_m[0]][0]<15){
$rw_a[$rw_m[0]][0]++;
$rw_a[$rw_m[0]][1]=$rw_m[1];
}
}
foreach($rw_a as $rw_i=>$rw_v){
if($rw_v[0]==15){
mysqli_query($db,"DELETE FROM room_winners WHERE num=".$rw_i." AND id<".$rw_v[1]);
}
}
// СТИРАЕМ СТАРЫЕ ВЫИГРЫШИ ФИКСИРОВАННОЙ КОМНАТЫ
$rfw_a=array();
$rfw_q=mysqli_query($db,"SELECT num,id FROM room_fix_winners ORDER BY id DESC") or die ('cant select room_fix_winners');
while($rfw_m=mysqli_fetch_row($rfw_q)){
if(!isset($rfw_a[$rfw_m[0]])){ $rfw_a[$rfw_m[0]]=array(0,0); }
if($rfw_a[$rfw_m[0]][0]<50){
$rfw_a[$rfw_m[0]][0]++;
$rfw_a[$rfw_m[0]][1]=$rfw_m[1];
}
}
foreach($rfw_a as $rfw_i=>$rfw_v){
if($rfw_v[0]==50){
mysqli_query($db,"DELETE FROM room_fix_winners WHERE num=".$rfw_i." AND id<".$rfw_v[1]);
}
}
// УДАЛЯЕМ СТАРЫЕ СООБЩЕНИЯ _______________
mysqli_query($db,"DELETE FROM dialog WHERE ddate<".($time-$d_mess_old)) or die('cant delete old dialog');
// ОПТИМИЗИРУЕМ ТАБЛИЦЫ
mysqli_query($db,"OPTIMIZE TABLE request,refs_profit,room_status,room_winners,room_fix_winners,users,news,data,dialog") or die('cant optimize common');
// СТИРАЕМ СТАРЫЕ СЕССИИ
$ses_t=$time-2*86400; // ЧЕРЕЗ СКОЛЬКО ДНЕЙ УДАЛЯТЬ СЕССИЮ НЕАКТИВНОГО ПОЛЬЗОВАТЕЛЯ
$ses_d=opendir('ses/');
while($ses_f=readdir($ses_d)){
if($ses_f!='.' && $ses_f!='..' && $ses_f!='.htaccess' && $ses_f!='index.html' && filemtime('ses/'.$ses_f)<$ses_t){
@unlink('ses/'.$ses_f);
}}
closedir($ses_d);
}


require("pages/head.php");



$pads=0;


if($mobile==0){ $left_width='width="310px"'; $pads=15; }


echo '<a name="tomenu"></a>
<table class="common" width="100%" cellpadding="0px" cellspacing="'.$pads.'px">
<tr>
<td class="left"'.$left_width.'>';


require("pages/left.php");


if($mobile==0){ echo '</td><td class="center">'; }
else{ echo '</td><tr><td class="center">'; }


// ______________________ СТРАНИЦЫ САЙТА ____________________


$nay=0;


if(!empty($page)){
if(in_array($page,$inc)){ $nay=1; require('pages/'.$page.'.php'); }
elseif($nay==0 && $u_id>0 && in_array($page,$inc_cab)){
$nay=1;
if($page=='dialog'){ require('im/dialog.php'); }
else{ require('cabinet/'.$page.'.php'); }
}
elseif($nay==0 && $u_id==0 && $page=='login'){ $nay=1; require('pages/login.php'); }
elseif($nay==0 && $u_id>0 && $u_id==$admin_id && in_array($page,$inc_adm)){
$nay=1;
$is_admin=1;
echo '<div class="a_common">';
if(empty($_SESSION['admin_ip']) || $_SESSION['admin_ip']!=$ip){ $is_admin=0; require('admin/a_auth.php'); }
if($is_admin==1){
$a_menu='<div class="a_menu_div">
<a class="a_menu_0" href="/?page=a_stat">Статистика</a>
<a class="a_menu_0" href="/?page=a_users">Пользователи</a>
<a class="a_menu_0" href="/?page=a_up">Пополнения</a>
<a class="a_menu_0" href="/?page=a_w">Выплаты</a>
<a class="a_menu_0" href="/?page=a_rp">Реферальные</a>
<a class="a_menu_0" href="/?page=a_news_list">Новости</a>
<a class="a_menu_0" href="/?page=a_options">Настройки</a>
</div>
<div id="a_message" class="a_message"></div>';
$a_menu=str_replace("\r\n",'',$a_menu);
$a_menu=str_replace("\n",'',$a_menu);
$a_menu=str_replace('0" href="/?page='.$page,'1" href="/?page='.$page,$a_menu);
echo $a_menu;
if($page=='im'){ require('im/im.php'); }
else{ require('admin/'.$page.'.php'); }
}
echo '</div>';
}

}


if($nay==0){ require('pages/main.php'); }


echo '</td></tr></table>';


if($u_id>0 && $mobile==0){
if($mydm['nrc']!=0 && $mydm['h_nrc']!=1){
$upcs='nrc=0';
$nrc_v=substr($mydm['nrc'],-2);
$nrc_1=array(2,3,4,22,23,24,32,33,34,42,43,44,52,53,54,62,63,64,72,73,74,82,83,84,92,93,94);
$nrc_2=array(1,21,31,41,51,61,71,81,91);
$nrc_w='ЧЕЛОВЕК';
if(in_array($nrc_v,$nrc_1)){ $nrc_w='ЧЕЛОВЕКА'; }
elseif(in_array($nrc_v,$nrc_2)){ $nrc_w='ЧЕЛОВЕК'; }
$td_nrc='<td><div class="upper_nrc_div"><div class="upper_nrc_title">Новые рефералы</div><div class="upper_nrc_value">'.$mydm['nrc'].' '.$nrc_w.'</div></div></td>';
}
if($mydm['nrs']!=0 && $mydm['h_nrs']!=1){
if(empty($upcs)){ $upcs='nrs=0'; }
else{ $upcs.=',nrs=0'; }
$td_nrs='<td><div class="upper_nrs_div"><div class="upper_nrs_title">Рефначисления</div><div class="upper_nrs_value">'.$mydm['nrs'].' РУБ.</div></div></td>';
}
if(!empty($upcs)){
mysqli_query($db,"UPDATE users SET $upcs WHERE uid='$u_id' LIMIT 1") or die('cant update upcs');
echo '<div id="upper" class="upper" style="display:block;" onClick="this.style.display=\'none\';"><table align="center" cellpadding="0px" cellspacing="0px"><tr>'.$td_nrc.$td_nrs.'</tr></table></div>';
}
}


if($u_id>0){


if(!empty($_POST['mess']) && !empty($mydm['mess'])){
mysqli_query($db,"UPDATE users SET mess='' WHERE uid='$u_id' LIMIT 1");
$mydm['mess']='';
}


if(!empty($mydm['mess'])){


echo '
<form id="form_mess" style="display:none;" method="POST"><input type="hidden" name="mess" value="1"></form>

<div id="mess" class="mess" onClick="with(document.getElementById(\'form_mess\')){ submit(); }">

<div class="mess_common">
<table align="center" cellpadding="0px" cellspacing="0px">
<tr>
<td class="mess_title"><div>Сообщение от администрации</div></td></td>
<td class="mess_close"><div title="Закрыть" onClick="with(document.getElementById(\'form_mess\')){ submit(); }">X<div/a></td>
</tr>
<tr>
<td colspan="2" class="mess_text"><div>'.$mydm['mess'].'</div></td>
</tr>
</table>
</div>

</div>';
}
}


echo '<div class="footer">';








if($mobile==0){
echo '
<section>
  <div class= "row">
    <div class="col-lg-3">
      <div class="main-foot-neim">
        <span>S-</span>LOTO
      </div>
        <div class="foot-content">
        Copyright © 2017-2018. Все права защищены. 
        «S-LOTO» - время быстрых лотерей. 
        </div>
    </div>
    <div class="col-lg-2">
      <div class="foot-main-menu">
        <h3>МЕНЮ</h3>
        <a href="/room/1">Играть</a>
        <a href="/news/">Новости</a>
        <a href="/contacts/">Контакты</a>
        <a href="/faq/">Вопрос - ответ</a>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="foot-main-menu">
        <h3>ПРИНИМАЕМ</h3>
        <div class="row">
          <div class="text-right col-lg-6">
            <img class="foot-oplata" src="/images/footer-visa.svg" alt="Виза">
            <img class="foot-oplata" src="/images/footer-qiwi.svg" alt="Киви">
            <img class="foot-oplata" src="/images/footer-yandex.svg" alt="Яндекс">
          </div>
          <div class="text-left col-lg-6">
          <img class="foot-oplata" src="/images/footer-mc.svg" alt="Мастер кард">
          <img class="foot-oplata" src="/images/footer-webmoney.svg" alt="Вебмани">
          <img class="foot-oplata" src="/images/footer-freekassa.svg" alt="Фрикасса">
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="foot-main-menu">
      <h3>Бонус</h3>
        <div class="foot-info">
          <div class="row">
            <div class="col-lg-3">
               <i class="fa fa-rub" aria-hidden="true"></i>
            </div>
            <div class="col-lg-8">
              <h4>Бесплатные рубли</h4>
              <p>Получайте до 1 рубля каждый час</p>
            </div>
          </div>
        </div>
        <div class="foot-info-2">
        <div class="row">
          <div class="col-lg-3">
            <i class="fa fa-users" aria-hidden="true"></i>
          </div>
          <div class="col-lg-8">
            <h4>Приглашайте друзей и зарабатывайте</h4>
            <p>Вы можете получить до 5% пожизненно</p>
          </div>
        </div>
        </div>
        <div class="foot-info-3">
        <div class="row">
          <div class="col-lg-3">
          <i class="fa fa-picture-o" aria-hidden="true"></i>
          </div>
          <div class="col-lg-8">
            <h4>Дизайн разработан командой S-LOTO</h4>
          </div>
        </div>
        </div>
      </div>
    </div>
  </div>
</section>









<div class="footer_own">© '.date('Y').'
<a href="//www.free-kassa.ru/"><img src="/images/16.png"></a>
<a href="https://www.fkwallet.ru"><img src="https://www.fkwallet.ru/assets/2017/images/btns/iconsmall_wallet9.png" title="Криптовалютный кошелёк"></a>
<a href="https://payeer.com/" target="_blank"><img src="/images/payeer_b.png"></a>
</div>';
}


echo '</div>';


echo '</body></html>';


?>

<a href="//www.free-kassa.ru/"><img src="//www.free-kassa.ru/img/fk_btn/9.png" title="Прием платежей на сайте"></a>
