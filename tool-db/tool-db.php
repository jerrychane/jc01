<?php
/**
 * #从火车头的access数据库导入到db中。v2.2013.1.8
 *
 * Release Notes
 * =============
 * -感谢晓东贡献修正非英语语种乱码问题到方法：找到php.ini。看看是否有extension=php_com_dotnet.dll，如果没有则添加，如果被注释，则取消注释，然后重启apche
 * -感谢晓东改进：文章乱序功能。即使采集的时候词是挨着的，导入到db到时候顺序也被打乱。
 * -从V2开始，Kiss全面兼容WP，增加了postname字段,新版db请用新版Kiss解析，不要弄错版本。
 * -当$dbname存在时，采用追加方式。如果不存在则创建。
 * -增加非空格区分(如中文)语种tag生成方法。注意44行左右，默认是按中文方式获取的，做俄语/英语等其他语言的时候注意用getTags()方法
 **/
date_default_timezone_set("Asia/Shanghai");
$file="F:\\xampp\\htdocs\\tool.com\\tool-pro\\test3.mdb";
$dbname="EN.project";
$fromTime=strtotime("2016/06/22 22:11:02");
$toTime=strtotime("2016/06/22 22:30:30");
$defTime=strtotime("2016/06/22 22:11:02");//设置特定的关键词发布时间靠前，与$fromTime相同
//配置使用哪些，这样，可以把一个数据库，生成多个DB
$where="and Id>0 and Id<100";//97751
$title_length="and Len([标题]) between 2 and 70"; // 标题长度15-70
$list_length="and Len([内容]) > 50";               //内容长度大于1000
header("Content-Type: text/html; charset=UTF-8");
set_time_limit(0);
ini_set('memory_limit', '1024M');
$db=getDB();

// $con = odbc_connect("Driver={Microsoft Access Driver (*.mdb)};Dbq={$file}", "", "") or die("can not connect");
// $data = odbc_exec($con, "select count(Id) as count from Content where 内容 is not null");

$dbc = new COM("ADODB.Connection",NULL,65001) or die("ADO connect failed!");
$dbc->open("DRIVER=Microsoft Access Driver (*.mdb);DBQ={$file}");
$data=$dbc->execute("select count(Id) as count from Content where 内容 is not null {$where}");
$count= intval($data->fields[0]->value);
if($count==0||$toTime-$fromTime==0)die("没有找到文章.或者发布开始时间和结束时间相同.");
$step=intval(($toTime-$fromTime)/$count);
echo "一共发现<b>{$count}</b>篇文章.根据设定,从<b>".date("Y/m/d H:i:s",$fromTime)."</b>-<b>".date("Y/m/d H:i:s",$toTime)."</b>发布完,每个{$step}秒发布一篇.<br>开始转换成DB格式";
$data->close();
//$data=$dbc->execute("select id,标题 as title,内容 as content from Content where 内容 is not null and 标题 is not null {$where}{$title_length}{$list_length} ORDER BY Rnd(id)");
$data=$dbc->execute("select id,img1,img2,img3,img4,标题 as title,描述 as description,内容 as content,特点 as features,应用 as application,参数 as parameter from Content where 内容 is not null and 标题 is not null {$where} ORDER BY Rnd(id)");
$db->exec("begin exclusive transaction");
$i=0;
while (!$data->eof){
	try{
		//TODO 在这里处理得到的每一篇文章.如翻译等.
		//这里只是一个测试。具体生成tag到算法需要按语种选择，没有tag是生成不了相关文章的
		$title=trim($data->fields["title"]->value);
		$content=trim($data->fields["content"]->value);
		$description=trim($data->fields["description"]->value);//描述
		$features=trim($data->fields["features"]->value);  //特点
		$application=trim($data->fields["application"]->value);  //应用
		$parameter=trim($data->fields["parameter"]->value);   //参数
		$img1=$data->fields["img1"]->value;
		$img2=$data->fields["img2"]->value;
		$img3=$data->fields["img3"]->value;
		$img4=$data->fields["img4"]->value;
        //$description=substr($description,0,193);
		$data->movenext();
$s=explode("<br>",$content);
$content="";
shuffle($s); 
$j=0;
foreach($s as $value){
			if ($j<35&&strlen(trim($value))>0){ ++$j;
			$value = str_replace(array('<ul>','</ul>')," ",$value);
			$value = str_replace("li>","p>",$value);
			$content.=$value;			
			}
			}
			
			
			if(empty($title)||empty($content))continue;
			$tag=getTags($title);
            $title=clearPoint($title);
			$content=clearPointContent($content);
			$title=$db->escapeString(ucwords($title));
			$content=$db->escapeString($content);
            $text_times=substr_count($content,"<h");//采集的单条记录中的H标签（yahoo是h3,ask是h2）
			$description=$db->escapeString($description);
			$tag=$db->escapeString($tag);
			$postname=$db->escapeString(strtolower(str_replace(' ', '-', $title).""));
			if($text_times>=1)        //采集记录大于8的插入数据库
			{	
			 $sql="insert into post values ('{$title}','{$tag}','{$description}','{$content}','{$title}',".($fromTime+($i++)*$step+mt_rand(0,$step)).",'{$postname}','{$fromTime}','{$features}','{$application}','{$parameter}','{$img1}','{$img2}','{$img3}',
			 '{$img4}')";
		    }
			else
			{
				continue;
			}
		   $db->exec($sql) or print($sql);
		   if($i%100==0){
			echo date("Y/m/d H:i:s")."&nbsp;&nbsp;".$i."&nbsp;:成功转换:{$title}<br>\n";
			ob_flush();flush();
		}
		
	}catch(Exception $e){
		echo $e;
	}


}
echo "{$i}&nbsp;转换完成<br>";
$db->exec("end transaction");
echo "更新tags<br/>";
updateTags();
echo "完成更新tags<br/>";
$db->close();
$data->close();
/**按空格区分的语种，比如英文/俄语等
 * @param unknown_type $title
* @return string
*/
function getTags($title){
	$tag="";
	$stopwords = array("a","able","about","above","above","abroad","across","according","accordingly","actually","after","afterwards","again","against","all","almost","alone","along","already","also","although","always","am","among","amongst","amoungst","amount","an","and","another","any","anyhow","anyone","anything","anyway","anywhere","are","around","as","at","back","be","became","because","become","becomes","becoming","been","before","beforehand","behind","being","below","beside","besides","between","beyond","bill","both","bottom","but","by","call","can","cannot","cant","co","con","could","couldnt","cry","de","describe","detail","do","done","does","down","due","during","each","eg","eight","either","eleven","else","elsewhere","empty","enough","etc","even","ever","every","everyone","everything","everywhere","except","few","fifteen","fify","fill","find","fire","first","five","for","former","formerly","forty","found","four","from","front","full","further","get","give","go","had","has","hasnt","have","he","hence","her","here","hereafter","hereby","herein","hereupon","hers","herself","him","himself","his","how","however","hundred","ie","if","in","inc","indeed","interest","into","is","it","its","itself","keep","last","latter","latterly","least","less","ltd","made","many","may","me","meanwhile","might","mine","more","moreover","most","mostly","move","much","must","my","myself","name","namely","neither","never","nevertheless","next","nine","no","nobody","none","noone","nor","not","nothing","now","nowhere","of","off","often","on","once","one","only","onto","or","other","others","otherwise","our","ours","ourselves","out","over","own","part","per","perhaps","please","put","rather","re","same","see","seem","seemed","seeming","seems","serious","several","she","should","show","side","since","sincere","six","sixty","so","some","somehow","someone","something","sometime","sometimes","somewhere","still","such","system","take","ten","than","that","the","their","them","themselves","then","thence","there","thereafter","thereby","therefore","therein","thereupon","these","they","thickv","thin","third","this","those","though","three","through","throughout","thru","thus","to","together","too","top","toward","towards","twelve","twenty","two","un","under","until","up","upon","us","very","via","was","we","well","were","what","whatever","when","whence","whenever","where","whereafter","whereas","whereby","wherein","whereupon","wherever","whether","which","while","whither","who","whoever","whole","whom","whose","why","will","with","within","without","would","yet","you","your","yours","yourself","yourselves","commonly","common");

	$title = str_replace($stopwords,"",$title);
	$tags=explode(" ",$title);
	foreach($tags as $t){
		$t=trim($t);
		if($t)$tag.=",".$t;
	}
	if($tag)$tag=substr($tag,1);//*/
	return $tag;
}
/**按字符分割的语种，比如中文。
 * @param unknown_type $title
* @return string
*/
function getTags2($title){
	$tag="";
	for ($i=0,$len=mb_strlen($title,"UTF-8");$i<$len;$i++){
		$s=mb_substr($title, $i,1,"UTF-8");
		if(trim($s))$tag.=",".$s;
	}
	if($tag)$tag=substr($tag,1);//*/
	return $tag;
}

//过滤多余标签,可自定义要去除的html标签
function filter_tag($content)
{
	#$rest=preg_replace("/<*/","",$content);
	#return $rest;
	
	$tag_filter = array("<html>","</html>","<body>","</body>","<head>","</head>","<title>","</title>","<pre>","</pre>","<u>","</u>","<b>","</b>","<i>","</i>","<tt>","</tt>","<cite>","</cite>","<em>","</em>","<strong>","</strong>","<font>","</font>","<BASEFONT>","</BASEFONT>","<big>","</big>","<small>","</small>","<br>","<blockquote>","<blockquote>","<dl>","</dl>","<dt>","</dt>","<dd>","</dd>","<ol>","</ol>","<ul>","</ul>","<div>","</div>","<a>","</a>");
    $content=str_replace($tag_filter,"",$content);
	
}
//创建一个空的DB
function getDB(){
	global $dbname;
	if(file_exists($dbname))echo "<h2>{$dbname}存在，做追加操作。</h2>";
	$db=new SQLite3($dbname,SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
	$db->exec("CREATE TABLE IF NOT EXISTS post (title varchar(256),tag varchar(256),description varchar(256),content TEXT,comment TEXT,posttime INTEGER,postname varchar(512),lists varchar(512),features TEXT,application TEXT,parameter TEXT,img1,img2,img3,img4)") && $db->exec("CREATE TABLE if not exists tag (k VARCHAR(128) PRIMARY KEY,v TEXT,count integer)") && $db->exec("create index if not exists indexcount on tag (count desc) ") && $db->exec("create index if not exists indextime on post (posttime desc)") && $db->exec("create UNIQUE index if not exists indexpostname on post (postname)");
	return $db;
}
   
function post($url,$postData){
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
	if($postData) {
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode($postData));
	}
	$curlResponse = curl_exec($ch);
	$curlErrno = curl_errno($ch);
	if ($curlErrno) {
		$curlError = curl_error($ch);
		echo $curlError;
		throw new Exception($curlError);
	}
	curl_close($ch);
	return $curlResponse;
}
function getData($sql){
	global $db;
	$result=$db->query($sql) or die("Error:".$sql);
	$ret=array();
	while($row=$result->fetchArray(SQLITE3_ASSOC))$ret[]=$row;
	unset($result);
	unset($row);
	return $ret;
}
function checkTables(){
	global $db;
	return $db->exec("CREATE TABLE IF NOT EXISTS post (title varchar(256),tag varchar(256),description varchar(256),content TEXT,comment TEXT,posttime INTEGER)") && $db->exec("CREATE TABLE if not exists tag (k VARCHAR(128) PRIMARY KEY,v TEXT,count integer)") && $db->exec("create index if not exists indexcount on tag (count desc) ") && $db->exec("create index if not exists indextime on post (posttime desc)");
}
function updateTags(){
	$tags=array();
	$sql="select rowid,tag from post";
	$data=getData($sql);
	foreach($data as $d){
		$postTags=explode(",",$d["tag"]);
		foreach($postTags as $pt){
			if(isset($tags[$pt]))$tags[$pt]=$tags[$pt].",".$d["rowid"];
			else $tags[$pt]=$d["rowid"];
		}
	}
	if(!empty($tags)){
		global $db;
		$db->exec("drop table tag");
		checkTables();
		$db->exec("begin exclusive transaction");
		foreach($tags as $k=>$v){
			$sql="insert into tag(k,v,count) values ('".$db->escapeString($k)."','{$v}',".(substr_count($v,",")+1).")";
			$db->exec($sql);
		}
		$db->exec("end transaction");
	}
	echo count($tags)." tags updated";
}

function clearPoint($title){ 
    $title= str_replace( 
     array("~" ,"!" ,"@" ,"#" ,"$" ,"%" ,"^" ,"+","&" ,"*" ,"," ,"." ,"?" ,";",":" ,'\'','"' ,"[" ,"]" ,"{" ,"}" ,"!" ,"￥" ,"……" ,
            "…" ,"、" ,"，" ,"。" ,"？" ,"；" ,"：","'","“" ,"”" ,"'" ,"【" ,"】" ,"～" ,"！" ,"＠" ,"＃" ,"＄" ,"％" ,"＾" ,"＆" ,"＊" ,"，" ,"．" ,"＜" ,"＞" ,"；" ,"：","＇","＂" ,"［" ,"］" ,"｛" ,"｝" ,"／" ,"＼" ,"（" ,"）" ,"(" ,")","《","》", '$','¿','×','SHAN BAO','&#xA0;','|','★','Aaron','aaron','AARON', 'Shan - Bao','alibaba','aron','Aron','Auspactor','B2B','binq','break day','Break day','BREAK DAY','Break-day','BREAK-DAY','Break-Day','break-day','Budowa dróg i mostów','budowa dróg i mostów','Carretera y Puente','carretera y puente','Carretera yPuente','CAT','cat','caterpillar','cathay','CATHAY','Cathay','CEMCO','Chabo inc','Construcción de Carretera y Puente','construcción de carretera y puente','crusher-saleVision','Czerwona Gwiazda','czerwona gwiazda','Dewalt','dsmac','Dsmac','DSMAC','Duoling','DW810','eBay','EC21','Ec21','ec21','Esong','FIFAN','Fifan','fifan','FLSmidth','Flsmidth','flsmidth','FLSMIDTH','FOX','Gandong','Garden Lawn Edging','George Chabo','George Salon','Gulin','Harison','Henan','Home;','Hong xing','hong xing','Hong Xing','HONG XING','Hongji','Hongxing','Hong-xing','householdgoods.com','Huayu','hưng thịnh','hưng thịnh','HXJQ','Hxjq','hxjq','Index','Inicio','Jianda','JIANDA','jianda','Jiangxi','Jianliang','jianliang','JIANLIANG','Jianqian','JIANQIAN','jianqian','Jianshe Luqiao','jianshe luqiao','JIANSHE LUQIAO','jianshe luqiao','Jianshe Luqiao','Jianshe Machinery','JIANSHE MACHINERY','jianshe machinery','JiansheLuqiao','jiansheluqiao','JIANSHELUQIAO','Jianyi','JIANYI','jianyi','Jingxin','jINGXIN','JINJIN','kefid','Kefid','KEFID','Kefid','kefid','Kırmızı yıldızı','Lianzhan','liming','Liming','LIMING','Liming','liming','LKM','Lkm','lkm','LockLift','LOKOTRACK','lokotrack','Lokotrack','LOKOTRACK','LUQIAO CO','luqiao co','Luqiao Co','Luqiao Co. Ltd.','Made-in-China','MARSMAN','MEG','MESTO','METSO','Mesto','metso','ｍｅｔｓｏ','Ｍｅｔｓｏ','ＭＥＴＳＯ','metso','Mirror On A','MnElite','MnPremium','MnStandard','mетсо','Nanning','Next:','ngôi sao màu đỏ','nitrolube','Nitrolube','nordberg','Nordberg','NORDBERG','ｎｏｒｄｂｅｒｇ','Ｎｏｒｄｂｅｒｇ','ＮＯＲＤＢＥＲＧ','nuvo','Nuvo','NUVO','Oman Flour Mills Co','Oman Flour Mills OFMI Company','Pegson','plascan','Powered By','Red Star','red star','RED STAR','REMco','rooi ster','Safe-T Lift','SAMA','SAMAC','SANDVIK','sandvik','Sandvik','ｓａｎｄｖｉｋ','Ｓａｎｄｖｉｋ','ＳＡＮＤＶＩＫ','SANDVIK','Sandvik','sandvik','SAOG','shan bao','shan Bao','SHAN BAO','Shan bao','SHANBAO','shanbao','Shanbao','shanBao','SHANBAO','Shanbao','shan-bao','Shanghai Jianshe Machinery','Shanghai MG','shanghai mg','shenbang','Shenzhen','shunky','Sinoma-Liyang','Smesauda','smidth','SMIDTH','Smidth','symon','Symon','SYMON','SYMONS','symons','Symons','ｓｙｍｏｎｓ','Ｓｙｍｏｎｓ','ＳＹＭＯＮＳ','SYMONS','Symons','symons','szczyt','Szczyt','SZCZYT','Telsmith','telsmith','TELSMITH','Terex','Tianjin','TradeKey','Trellex','TRELLEX','trellex','TRIO','Vipeak','VIRGIN','Virgin','virgin','Westpro','Xây dựng cầu đường','xinyun','yi fan','Yi Fan','YI FAN','YIFAN','Yifan','yifan','Zhecheng Jingxin','Zhengzhou','ZhongKe','Бao','бao','БaО','бaО','БАo','бАo','БАО','бАО','Ифань','ифань','ИФАНЬ','ифань','Ифань','ифань','ИФАНЬ','Кефид','КЕФИД','КЕФИД','Кефид','кефид','КЕФИД','Лимин','лимин','ЛИМИН','Лиминг','лиминг','ЛИМИНГ','лиминг','Локотраск','локотраск','ЛОКОТРАСК','Локотраск','Метсо','метсо','МЕТСО','Метсо','Нордберг','НОРДБЕРГ','Нордберг','нордберг','Саймонс','саймонс','САЙМОНС','Сандвик','сандвик','САНДВИК','треллекс','ТРЕЛЛЕКС','Шанбао','ШАНБАО','шанбао','Шаньбао','шаньбао','ШАНЬБАО','شان باو','本文章由','创申','鼎盛','弘泰','红星','环球博客','建设路桥','卡特','科菲达','黎明','龙阳','论坛','美卓','山宝','山启','山特维克','世赫','网站描述:','西蒙斯','鑫运','一帆','中联','卓赛'),
'', 
        $title 
    ); 
    $title= str_replace( 
        array("  ","-","_","\\","/"), 
        ' ', $title 
    ); 
    $title= str_replace(array("á","í","é","ó","ú","ñ","Á","Í","É","Ó","Ú","Ñ","ç","ã","à","â","ê","ô","õ","ü"),
	                 array("a","i","e","o","u","n","a","i","e","o","u","n","c","a","a","a","e","o","o","u"),
	$title);
    $title= str_replace(array("а","б","в","г","д","е","ё","ж","з","и","й","к","л","м","н","о","п","р","с","т","у","ф","х","ц","ч","ш","я","ю","щ","щ","э","ъ","ь","А","Б","В","Г","Д","Е","Ё","Э","Ж","З","И","Й","К","Л","М","Н","О","П","Р","С","Т","У","Ф","Х","Ц","Ч","Ш","Щ","Ы","Ю","Я","ы"),array("a","b","v","g","d","e","e","zh","z","i","j","k","l","m","n","o","p","r","s","t","u","f","x","c","ch","s","ya","yu","sch","y","e","","","A","B","V","G","D","E","E","E","J","Z","I","I","K","L","M","N","O","P","R","S","T","U","F","H","C","CH","SH","SH","Y","YU","YA","s"),$title);
    $title = strtolower(strip_tags(trim($title)));    
	$title = explode(' ',$title);  
	$title = implode(' ',array_filter($title));
	return $title;
} 
//采集内容整理
function clearPointContent($content){ 
    $content= str_replace( 
     array("~" ,"!" ,"@" ,"#" ,"$" ,"%" ,"^" ,"+","&" ,"*" ,"," ,"?" ,";",":" ,'\'',"{" ,"}" ,"!" ,"￥" ,"……" , "…" ,"、" ,"，" ,"。" ,"？" ,"；" ,"：","'","“" ,"”" ,"'" ,"【" ,"】" ,"～" ,"！" ,"＠" ,"＃" ,"＄" ,"％" ,"＾" ,"＆" ,"＊" ,"，" ,"＜" ,"＞" ,"；" ,"：","＇","｛" ,"｝" ,"／" ,"＼" ,"（" ,"）" ,"(" ,")","《","》", '$','¿','×','SHAN BAO','&#xA0;','|','★','Aaron','aaron','AARON', 'Shan - Bao','alibaba','aron','Aron','Auspactor','B2B','binq','break day','Break day','BREAK DAY','Break-day','BREAK-DAY','Break-Day','break-day','Budowa dróg i mostów','budowa dróg i mostów','Carretera y Puente','carretera y puente','Carretera yPuente','CAT','cat','caterpillar','cathay','CATHAY','Cathay','CEMCO','Chabo inc','Construcción de Carretera y Puente','construcción de carretera y puente','crusher-saleVision','Czerwona Gwiazda','czerwona gwiazda','Dewalt','dsmac','Dsmac','DSMAC','Duoling','DW810','eBay','EC21','Ec21','ec21','Esong','FIFAN','Fifan','fifan','FLSmidth','Flsmidth','flsmidth','FLSMIDTH','FOX','Gandong','Garden Lawn Edging','George Chabo','George Salon','Gulin','Harison','Henan','Home;','Hong xing','hong xing','Hong Xing','HONG XING','Hongji','Hongxing','Hong-xing','householdgoods.com','Huayu','hưng thịnh','hưng thịnh','HXJQ','Hxjq','hxjq','Index','Inicio','Jianda','JIANDA','jianda','Jiangxi','Jianliang','jianliang','JIANLIANG','Jianqian','JIANQIAN','jianqian','Jianshe Luqiao','jianshe luqiao','JIANSHE LUQIAO','jianshe luqiao','Jianshe Luqiao','Jianshe Machinery','JIANSHE MACHINERY','jianshe machinery','JiansheLuqiao','jiansheluqiao','JIANSHELUQIAO','Jianyi','JIANYI','jianyi','Jingxin','jINGXIN','JINJIN','kefid','Kefid','KEFID','Kefid','kefid','Kırmızı yıldızı','Lianzhan','liming','Liming','LIMING','Liming','liming','LKM','Lkm','lkm','LockLift','LOKOTRACK','lokotrack','Lokotrack','LOKOTRACK','LUQIAO CO','luqiao co','Luqiao Co','Luqiao Co. Ltd.','Made-in-China','MARSMAN','MEG','MESTO','METSO','Mesto','metso','ｍｅｔｓｏ','Ｍｅｔｓｏ','ＭＥＴＳＯ','metso','Mirror On A','MnElite','MnPremium','MnStandard','mетсо','Nanning','Next:','ngôi sao màu đỏ','nitrolube','Nitrolube','nordberg','Nordberg','NORDBERG','ｎｏｒｄｂｅｒｇ','Ｎｏｒｄｂｅｒｇ','ＮＯＲＤＢＥＲＧ','nuvo','Nuvo','NUVO','Oman Flour Mills Co','Oman Flour Mills OFMI Company','Pegson','plascan','Powered By','Red Star','red star','RED STAR','REMco','rooi ster','Safe-T Lift','SAMA','SAMAC','SANDVIK','sandvik','Sandvik','ｓａｎｄｖｉｋ','Ｓａｎｄｖｉｋ','ＳＡＮＤＶＩＫ','SANDVIK','Sandvik','sandvik','SAOG','shan bao','shan Bao','SHAN BAO','Shan bao','SHANBAO','shanbao','Shanbao','shanBao','SHANBAO','Shanbao','shan-bao','Shanghai Jianshe Machinery','Shanghai MG','shanghai mg','shenbang','Shenzhen','shunky','Sinoma-Liyang','Smesauda','smidth','SMIDTH','Smidth','symon','Symon','SYMON','SYMONS','symons','Symons','ｓｙｍｏｎｓ','Ｓｙｍｏｎｓ','ＳＹＭＯＮＳ','SYMONS','Symons','symons','szczyt','Szczyt','SZCZYT','Telsmith','telsmith','TELSMITH','Terex','Tianjin','TradeKey','Trellex','TRELLEX','trellex','TRIO','Vipeak','VIRGIN','Virgin','virgin','Westpro','Xây dựng cầu đường','xinyun','yi fan','Yi Fan','YI FAN','YIFAN','Yifan','yifan','Zhecheng Jingxin','Zhengzhou','ZhongKe','Бao','бao','БaО','бaО','БАo','бАo','БАО','бАО','Ифань','ифань','ИФАНЬ','ифань','Ифань','ифань','ИФАНЬ','Кефид','КЕФИД','КЕФИД','Кефид','кефид','КЕФИД','Лимин','лимин','ЛИМИН','Лиминг','лиминг','ЛИМИНГ','лиминг','Локотраск','локотраск','ЛОКОТРАСК','Локотраск','Метсо','метсо','МЕТСО','Метсо','Нордберг','НОРДБЕРГ','Нордберг','нордберг','Саймонс','саймонс','САЙМОНС','Сандвик','сандвик','САНДВИК','треллекс','ТРЕЛЛЕКС','Шанбао','ШАНБАО','шанбао','Шаньбао','шаньбао','ШАНЬБАО','شان باو','本文章由','创申','鼎盛','弘泰','红星','环球博客','建设路桥','卡特','科菲达','黎明','龙阳','论坛','美卓','山宝','山启','山特维克','世赫','网站描述:','西蒙斯','鑫运','一帆','中联','卓赛'),
'', $content ); 
	return $content;
} 
?>