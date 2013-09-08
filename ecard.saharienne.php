<? /* Saharienne back office (c+) tobozo may 2011 */

$tmpFolder = "tmp/";
$outFolder = "out/";

$outPath = "/ecard/out/";


if(isset($_GET['view_source'])) {
  highlight_file(__FILE__);
  exit(0);
}

/*  DOWNLOAD IMAGE FILE */
if(eregi("download", $_SERVER['REQUEST_URI'])) {
  // download call
  $fileName = end(explode("/", $_SERVER['REQUEST_URI']));

  if(!preg_match("/^[a-z0-9]{13}_message\.jpg$/i", $fileName)) {
    $fileName = "dunes.jpg";
  };

  if(!file_exists($outFolder.$fileName)) {
    die();
  }

  header("Content-type: application/force-download");
  header("Content-Disposition: attachment; filename=".$fileName);
  readfile($outFolder.$fileName);
  exit;
}


if(eregi('sendEcard', $_SERVER['REQUEST_URI'])) {
  $fileName = isset($_GET['image']) ? $_GET['image'] : die("response=fail&reason=no_image_provided"); // 'dunes.jpg';

  $lang = isset($_GET['lang']) ? $_GET['lang'] : 'fr';

  switch($lang) {
    case 'fr':
    case 'it':
    case 'es':
    case 'en':
      $ecardHtmlTpl = 'ecard.'.$lang.'.html.tpl';
      $ecardTxtTpl  = 'ecard.'.$lang.'.txt.tpl';
    break;
    default:
      $ecardHtmlTpl = 'ecard.html.tpl';
      $ecardTxtTpl  = 'ecard.txt.tpl';
  }


  if(!preg_match("/^[a-z0-9]{13}_message\.jpg$/i", $fileName)) {
    die("response=fail&reason=preg_failed_on_filename");
    $fileName = "dunes.jpg";
  };

  if(!file_exists($outFolder.$fileName)) {
    die("response=fail&reason=image_vanished");
  }

  if(!isset($_GET['recipient'])) {
    die("response=fail&message=No+email+provided");
  }
  if(!filter_var($_GET['recipient'], FILTER_VALIDATE_EMAIL)) {
    die("response=fail&message=Invalid+Email+Address");
  }
  if(trim($_GET['senderName'])=='') {
    die("response=fail&message=No+Sender+Name");
  }

  require("/home/sites_web/apache/phplib/phpmailer-lite.class.php");

  $mail = new PHPMailerLite;

  $mail->From = "noreply";
  $mail->Sender = "noreply";
  $mail->FromName = "Sand Text Ecard";
  $mail->AddCustomHeader("X-Originating-IP:".$_SERVER['REMOTE_ADDR']); // emulate hotmail x-originating-ip
  $mail->AddAddress($_GET['recipient']);
  $mail->AddAttachment($outFolder.$fileName, "ecard_message.jpg");
  $mail->Subject = "Sand Text Ecard";
  $mail->CharSet = 'UTF-8';
  $mail->Body = sprintf(file_get_contents($ecardHtmlTpl),
       utf8_encode(trim($_GET['senderName'])),
       $_SERVER['REMOTE_ADDR']
  );
  $mail->isHTML(true);
  $mail->AltBody = sprintf(file_get_contents($ecardTxtTpl),
       utf8_encode(trim($_GET['senderName'])),
       $_SERVER['REMOTE_ADDR']
  );

  if(!$mail->send()) {
    die("response=fail&message=".$mail->getError());
  } else {
    die("response=success");
  }

}

// auto-enable back office mode
$mode = 'bo';

//$mode = "prod";


$colorlight = "#e4bca3"; // "#FDA34D";
$colordark  = "#4b2c27"; //"#6A2B0A";

$tmpName = uniqid(); // prefix for all file names
$layerFile = "dunes.jpg";
$stampFile = $tmpFolder.$tmpName."_stamp.jpg";
$maskFile  = $tmpFolder.$tmpName."_mask.jpg";
$outFile   = $outFolder.$tmpName."_message.jpg";
$outUrl    = $outPath.$tmpName."_message.jpg";


$size = $mode=='bo' ? getimagesize($layerFile) : array(
    '0' => '952',
    '1' => '389',
    '2' => '2',
    '3' => 'width="952" height="389"',
    'bits' => '8',
    'channels' => '3',
    'mime' => 'image/jpeg',
);

//die(print_r($size,1));

$imgwidth  = $size[0];
$imgheight = $size[1];
$imgdim = $imgwidth."x".$imgheight;


$fonts = array_merge(glob("*.ttf"), glob("*.TTF"));
$defaultFont = "sand_complete.ttf";
$font = $mode=='bo'
            ? (isset($_GET['font'])&&in_array($_GET['font'], $fonts)) ? $_GET['font'] : $defaultFont
            : $defaultFont;


$defaultFontSize = '45';
$fontsize = $mode=='bo'
                ? (isset($_GET['fontSize'])&&(int)$_GET['fontSize']>0&&(int)$_GET['fontSize']<100) ? (int)$_GET['fontSize'] : $defaultFontSize
                : $defaultFontSize;


$defaultOffsetX = 1;
$defaultOffsetY = 1;
$offsetX = $mode=='bo'
                ? (isset($_GET['offsetX'])&&(int)$_GET['offsetX']>0&&(int)$_GET['offsetX']<100) ? (int)$_GET['offsetX'] : $defaultOffsetX
                : $defaultOffsetX;
$offsetY = $mode=='bo'
                ? (isset($_GET['offsetY'])&&(int)$_GET['offsetY']>0&&(int)$_GET['offsetY']<100) ? (int)$_GET['offsetY'] : $defaultOffsetY
                : $defaultOffsetY;
$offsetX1 = $offsetX+1;
$offsetX2 = $offsetX-1;
$offsetY1 = $offsetY+1;
$offsetY2 = $offsetY-1;

//$perspectiveInfo = "0,0 175,215 30,365 0,365 550,0 375,195 550,365 550,365";
//$perspectiveInfo = "0,0 161,171 30,365 29,335 550,0 429,148 550,365 516,329";


$perspectiveInfo = "0,0 384,161 0,389 231,386 952,0 678,136 952,389 952,381";


if($mode=='bo' && isset($_GET['perspectiveInfo'])) {
  if(preg_match("/^[0-9, ]+$/", $_GET['perspectiveInfo'])) {
    $perspectiveInfo = $_GET['perspectiveInfo'];
  }
}

if(isset($_GET['message']) && $_GET['message']!='') {
  $textFile = $tmpName.".txt";
  $text = utf8_decode(str_replace('****', "\n", utf8_encode($_GET['message'])));
  $lines = explode("\n", $text);

  // 4 lines max
  if(count($lines)>4) {
    die("response=fail&reason=text_spoof_attempt");
  }
  // no more than 33 chars per line (utf8)
  foreach($lines as $n => $line) {
    if(strlen($line)>33) {
      die("response=fail&reason=line_too_long");
    }
  }
  file_put_contents($textFile, $text);
} else {

 if($mode=='bo') {


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <link rel="shortcut icon" href="/favicon.ico" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Leave your (short) message in the sand</title>
    <script type="text/javascript" src="/js/jquery-1.5.1.min.js"></script>
    <script type="text/javascript" src="/js/jquery-ui-1.8.12.custom.min.js"></script>
    <script type="text/javascript" src="/js/jquery.svg.js"></script>
    <script type="text/javascript" src="/js/jquery.drawinglibrary.js"></script>
    <style type="text/css">

      #submit {
        display:inline-block;
        padding-left:10px;
        padding-right:10px;
        padding-top:2px;
        padding-bottom:2px;
        border:1px solid black;
        margin:2px;
        cursor:pointer;
      }
      .canvas {
        position: relative;
        height: 800px;
        width: 1000px;
      }
      .draggablepoint {
       cursor:move;
      }
    </style>
</head>
<body>

<form name="dummy" action="#" method="post">
  <fieldset style="display:inline">
    <legend>User values</legend>
  <!-- Note : max 18 chars by line<br /> -->

  Line 1 : <input type="text" id="line1" name="line1" maxlength=33 size=40 /><br />
  Line 2 : <input type="text" id="line2" name="line2" maxlength=33 size=40 /><br />
  Line 3 : <input type="text" id="line3" name="line3" maxlength=33 size=40 /><br />
  Line 4 : <input type="text" id="line4" name="line4" maxlength=33 size=40 /><br />
  </fieldset>
  <fieldset style="position:absolute;display:inline">
    <legend>Back office values</legend>
  Font face : <select id="font" name="font">
  <?
   foreach($fonts as $num => $fontName) {
     if($fontName == $defaultFont) {
       ?><option selected="selected" value="<?=$fontName;?>"><?=strtolower($fontName);?> (default)</option><?
     } else {
       ?><option value="<?=$fontName;?>"><?=strtolower($fontName);?></option><?
     }
   }
  ?>
  </select><br />
  Font Size : <input type="text" id="fontSize" name="fontSize" value="<?=$fontsize;?>" size="2" /><br />
  Text offset X: <input type="text" name="offsetX" id="offsetX" value="<?=$defaultOffsetX;?>" size=2 /> (min=0, max=100)<br />
  Text offset Y: <input type="text" id="offsetY" name="offsetY" value="<?=$defaultOffsetY;?>" size=2 /> (min=0, max=100)<br />
  Perspective Info : <input style="font-size:70%" type="text" id="perspectiveInfo" name="perspectiveInfo" value="<?=$perspectiveInfo;?>" size="55" />
  </fieldset>

  <br />
  <span id="submit">Submit</span>
  <br /><br />
</form>
<script type="text/javascript">

   var perspectiveInfo = "<?=$perspectiveInfo;?>";

   $(document).ready(function() {
     $("#submit").click(function() {
       var message = $("#line1").val() + "****" + $("#line2").val() + "****" + $("#line3").val()+ "****" + $("#line4").val();
       var perspectiveInfo = $("#perspectiveInfo").val();
       var offsetVars = "&offsetX="+$("#offsetX").val()+"&offsetY="+$("#offsetY").val();
       var fontsize = "&fontSize="+$("#fontSize").val();
       var fontface = "&font="+$("#font").val();
       $.get("?perspectiveInfo="+perspectiveInfo+offsetVars+fontsize+fontface+"&message="+encodeURIComponent(message), function(data) {
         if(data!='') {
           $("#imageHolder").empty();
           $("<img />").attr("src", data).appendTo("#imageHolder");
         }
       });
     });

     var p_points = perspectiveInfo.split(" ");
     var p = [];
     var t = [];

     var pnum = 0;
     var tnum = 0;

     for(pair in p_points) {
       if($.trim(p_points[pair])!="") {
         coords = p_points[pair].split(',');
         x = coords[0];
         y = coords[1];

         point = [x,y];

         if(pair%2==0) {
           p.push(point);
           id="p"+pnum;
           pnum++;
         } else {
           t.push(point);
           id="t"+tnum;
           tnum++;
         }

         $("<img src=bullet.png class=draggablepoint alt='"+pair+"'>").attr("id", id).css({position:"absolute", left:(x-3.5)+"px", top:(y-3.5)+"px"}).appendTo($("#drawPanel"));

       }
     }

     drawBoxes();

     $("img.draggablepoint").draggable({
       start:function() {
           offset = $(this).position();
           $(this).attr("oldoffset", offset.left+","+offset.top);
       },
       stop:function() {
         pointid   = parseInt(this.id.charAt(1));
         pointtype = this.id.charAt(0);

         offset = $(this).position();

         switch(pointtype) {
           case 'p':
             p[pointid][0] = offset.left +3.5;
             p[pointid][1] = offset.top  +3.5;
             if(p[pointid][0]>$("#imageHolder").width()) {
               p[pointid][0] = $("#imageHolder").width();
               $(this).css({left:(p[pointid][0]-3.5)+"px"});
             }
             if(p[pointid][1]>$("#imageHolder").height()) {
               p[pointid][1] = $("#imageHolder").height();
               $(this).css({top:(p[pointid][1]-3.5)+"px"});
             }

             if(p[pointid][0]<0) {
               p[pointid][0] = 0;
               $(this).css({left:"-3.5px"});
             }
             if(p[pointid][1]<0) {
               p[pointid][1] = 0;
               $(this).css({top:"-3.5px"});
             }
           break;
           case 't':
             t[pointid][0] = offset.left +3.5;
             t[pointid][1] = offset.top  +3.5;
             if(t[pointid][0]>$("#imageHolder").width()) {
               t[pointid][0] = $("#imageHolder").width();
               $(this).css({left:(t[pointid][0]-3.5)+"px"});
             }
             if(t[pointid][1]>$("#imageHolder").height()) {
               t[pointid][1] = $("#imageHolder").height();
               $(this).css({top:(t[pointid][1]-3.5)+"px"});
             }

             if(t[pointid][0]<0) {
               t[pointid][0] = 0;
               $(this).css({left:"-3.5px"});
             }
             if(t[pointid][1]<0) {
               t[pointid][1] = 0;
               $(this).css({top:"-3.5px"});
             }
           break;

         }

         $("#mydiv").empty();
         drawBoxes();
         calcNewCoords();
         $("#submit").click();
       }
     });

     $("#line1,#line2,#line3,#line4").keyup(function() {
       $(this).val($(this).val().toUpperCase());
     });


    function drawBoxes() {
         $("#mydiv").drawLine(p[0][0], p[0][1], p[1][0], p[1][1], {color:"blue"});
         $("#mydiv").drawLine(p[1][0], p[1][1], p[3][0], p[3][1], {color:"blue"});
         $("#mydiv").drawLine(p[2][0], p[2][1], p[3][0], p[3][1], {color:"blue"});
         $("#mydiv").drawLine(p[2][0], p[2][1], p[0][0], p[0][1], {color:"blue"});

         $("#mydiv").drawLine(t[0][0], t[0][1], t[1][0], t[1][1], {color:"green"});
         $("#mydiv").drawLine(t[1][0], t[1][1], t[3][0], t[3][1], {color:"green"});
         $("#mydiv").drawLine(t[2][0], t[2][1], t[3][0], t[3][1], {color:"green"});
         $("#mydiv").drawLine(t[2][0], t[2][1], t[0][0], t[0][1], {color:"green"});
    }

    function calcNewCoords() {
       $("#perspectiveInfo").val(
         Math.floor(p[0][0])+','+Math.floor(p[0][1])+" "+
         Math.floor(t[0][0])+','+Math.floor(t[0][1])+" "+
         Math.floor(p[1][0])+','+Math.floor(p[1][1])+" "+
         Math.floor(t[1][0])+','+Math.floor(t[1][1])+" "+
         Math.floor(p[2][0])+','+Math.floor(p[2][1])+" "+
         Math.floor(t[2][0])+','+Math.floor(t[2][1])+" "+
         Math.floor(p[3][0])+','+Math.floor(p[3][1])+" "+
         Math.floor(t[3][0])+','+Math.floor(t[3][1])+" "
       );
    }


   });
</script>
<div id="drawPanel" style="position:relative">
    <div id="imageHolder" style="position:absolute"><img src="<?=$layerFile;?>" /></div>
    <div id="mydiv" class="canvas"></div>
</div>
</body>
</html><?
  }
  exit(0);

}


$cmd[] = sprintf("convert -size $imgdim xc:transparent -font %s -pointsize %s -fill '%s' -gravity South -annotate +$offsetX2+$offsetY2 @%s -fill '%s' -gravity South -annotate +$offsetX1+$offsetY1 @%s -fill transparent -gravity South -annotate +$offsetX+$offsetY @%s %s ",
   $font,
   $fontsize,
   $colorlight,
   $textFile,
   $colordark,
   $textFile,
   $textFile,
   $stampFile
);

$cmd[] = sprintf("convert %s -matte -mattecolor black -virtual-pixel black -distort Perspective '$perspectiveInfo' %s",
   $stampFile,
   $stampFile
);
$cmd[] = sprintf("convert -size $imgdim xc:black -font %s -pointsize %s -fill white -gravity South -annotate +$offsetX2+$offsetY2 @%s -fill white -gravity South -annotate +$offsetX1+$offsetY1 @%s -fill black -gravity South -annotate +$offsetX+$offsetY @%s %s",
   $font,
   $fontsize,
   $textFile,
   $textFile,
   $textFile,
   $maskFile
);
$cmd[] = sprintf("convert %s -matte -mattecolor black -virtual-pixel black -distort Perspective '$perspectiveInfo' %s",
   $maskFile,
   $maskFile
);
$cmd[] = sprintf("convert %s %s %s -composite %s",
   $layerFile,
   $stampFile,
   $maskFile,
   $outFile
);


foreach($cmd as $num => $cmdline) {
  echo exec($cmdline);
}

unlink($stampFile);
unlink($maskFile);
unlink($textFile);


if(isset($_GET['fla'])) {
  echo "&response=".$outFile;
} else {
  echo $outFile;
}

/*

convert -size 550x366 xc:transparent -font SAND____.TTF -pointsize 36 -fill '#FDA34D' -gravity South -annotate +24+64 'BLAH BLAH' -fill '#6A2B0A' -gravity South -annotate +26+66 'BLAH BLAH' -fill transparent -gravity South -annotate +25+65 'BLAH BLAH' trans_stamp.jpg

convert trans_stamp.jpg -matte -mattecolor black -virtual-pixel black -distort Perspective '0,0 175,195  0,365 0,365  550,0 375,195  550,365 550,365' trans_stamp.jpg

convert -size 550x366 xc:black -font SAND____.TTF -pointsize 36 -fill white -gravity South -annotate +24+64 'BLAH BLAH' -fill white -gravity South -annotate +26+66 'BLAH BLAH' -fill black -gravity South -annotate +25+65 'BLAH BLAH' mask_mask.jpg

convert mask_mask.jpg -matte -mattecolor black -virtual-pixel black -distort Perspective '0,0 175,195  0,365 0,365  550,0 375,195  550,365 550,365' mask_mask.jpg

convert dunes.jpg  trans_stamp.jpg  mask_mask.jpg -composite  mask_result2.jpg

*/
