<?php
include('../../config.php');
include($FANNIE_ROOT.'src/SQLManager.php');
include('../db.php');

include('memAddress.php');
include('header.html');
$memnum= $_GET['memnum'];
$username = validateUserQuiet('editmembers');
if (!$username) $username = validateUserQuiet('editmembers_csc');
if (!$username){
  echo "<a href=\"{$FANNIE_URL}auth/ui/loginform.php?redirect={$FANNIE_URL}legacy/members/testEdit.php?memnum=$memnum\">You must be Logged in to edit</a>";
}
else {

//echo $memnum;
//$lName = $_POST['lastName'];
//$fName = $_POST['firstName'];/*
//$username = $_COOKIE['ow-loginname'];

?>
<html>
<head>

</head>
<body 
    bgcolor="#66CC99" 
    leftmargin="0" topmargin="0" 
    marginwidth="0" marginheight="0" 
    onload="MM_preloadImages(
        '../images/memOver.gif',
        '../images/memUp.gif',
        '../images/repUp.gif',
        '../images/itemsDown.gif',
        '../images/itemsOver.gif',
        '../images/itemsUp.gif',
        '../images/refUp.gif',
        '../images/refDown.gif',
        '../images/refOver.gif',
        '../images/repDown.gif',
        '../images/repOver.gif'
    )"
>

<table width="660" height="111" border="0" cellpadding="0" cellspacing="0" bgcolor="#66cc99">
  <tr>
    <td colspan="2"><h1><img src="../images/newLogo_small1.gif" /></h1></td>
    <!-- <td colspan="9" valign="middle"><font size="+3" face="Papyrus, Verdana, Arial, Helvetica, sans-serif">PI Killer</font></td>
  --> </tr>
  <tr>
    <td colspan="11" bgcolor="#006633"><!--<a href="memGen.php">--><img src="../images/general.gif" width="72" height="16" border="0" /><a href="memEquit.php"><img src="../images/equity.gif" width="72" height="16" border="0" /></a><a href="memAR.php"><img src="../images/AR.gif" width="72" height="16" border="0" /></a><a href="memControl.php"><img src="../images/control.gif" width="72" height="16" border="0" /></a><a href="memDetail.php"><img src="../images/detail.gif" width="72" height="16" border="0" /></a></td>
  </tr>
  <tr>
    <td colspan="9"><a href="mainMenu.php" target="_top" onclick="MM_nbGroup('down','group1','Members','../images/memDown.gif',1)" onmouseover="MM_nbGroup('over','Members','../images/memOver.gif','../images/memUp.gif',1)" onmouseout="MM_nbGroup('out')"><img src="../images/memDown.gif" alt="" name="Members" border="0" id="Members" onload="MM_nbGroup('init','group1','Members','../images/memUp.gif',1)" /></a><a href="javascript:;" target="_top" onclick="MM_nbGroup('down','group1','Reports','../images/repDown.gif',1)" onmouseover="MM_nbGroup('over','Reports','../images/repOver.gif','../images/repUp.gif',1)" onmouseout="MM_nbGroup('out')"><img src="../images/repUp.gif" alt="" name="Reports" width="81" height="62" border="0" id="Reports" onload="" /></a><a href="javascript:;" target="_top" onClick="MM_nbGroup('down','group1','Items','../images/itemsDown.gif',1)" onMouseOver="MM_nbGroup('over','Items','../images/itemsOver.gif','../images/itemsUp.gif',1)" onMouseOut="MM_nbGroup('out')"><img name="Items" src="../images/itemsUp.gif" border="0" alt="Items" onLoad="" /></a><a href="javascript:;" target="_top" onClick="MM_nbGroup('down','group1','Reference','../images/refDown.gif',1)" onMouseOver="MM_nbGroup('over','Reference','../images/refOver.gif','../images/refUp.gif',1)" onMouseOut="MM_nbGroup('out')"><img name="Reference" src="../images/refUp.gif" border="0" alt="Reference" onLoad="" /></a></td>

</tr>
</table>

<? 
if(isset($more)){
    echo "hi";
}
//echo $memID;
//echo $lName;
//echo $fName;

        //echo $memID;

    addressFormLimited($memnum);

}
?>


</body>
</html>
