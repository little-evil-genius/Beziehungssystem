<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
 
// HOOKS
// Profil - Hinzufügen und Ausgabe
$plugins->add_hook("member_profile_end", "relations_member_profile_end");
// Index-Alert
$plugins->add_hook('global_intermediate', 'relations_alert');
// Usercp - Accepted Side
$plugins->add_hook('usercp_start', 'relations_usercp');
// was passiert beim löschen
$plugins->add_hook("admin_user_users_delete_commit_end", "relations_user_delete");
// MyAlerts
if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
	$plugins->add_hook("global_start", "relations_myalert_alerts");
}

// Die Informationen, die im Pluginmanager angezeigt werden
function relations_info()
{
	return array(
		"name"		=> "Beziehungssystem",
		"description"	=> "Das Plugin erweitert das Board um ein Beziehungssystem. User können andere Accounts zu ihrer Beziehungskiste hinzufügen. Je nach Einstellungen können User auch noch NPCs hinzufügen.",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function relations_install()
{
    global $db, $cache, $mybb;

    // Datenbank-Tabelle RELATIONS erstellen
	$db->query("CREATE TABLE ".TABLE_PREFIX."relations(
        `rid` int(10) NOT NULL AUTO_INCREMENT,
        `relation_by` int(11) NOT NULL,
        `relation_with` int(11) NOT NULL,
		`type` VARCHAR(500) NOT NULL,
		`relationship` VARCHAR(500) NOT NULL,
		`description` VARCHAR(2500) NOT NULL,
		`npc_name` VARCHAR(500) NOT NULL,
		`npc_age` VARCHAR(500) NOT NULL,
		`npc_home` VARCHAR(500) NOT NULL,
		`npc_relation` VARCHAR(500) NOT NULL,
		`npc_search` VARCHAR(1000) NOT NULL,
        `accepted` int(1) NOT NULL,
        PRIMARY KEY(`rid`),
        KEY `rid` (`rid`)
        )
        ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1
        ");

        $db->query("ALTER TABLE `".TABLE_PREFIX."users` ADD `relations_pn` int(11) NOT NULL DEFAULT '0';");

    // EINSTELLUNGEN HINZUFÜGEN
    $setting_group = array(
        'name'          => 'relations',
        'title'         => 'Beziehungssystem',
        'description'   => 'Einstellungen für das Beziehungssystem',
        'disporder'     => 1,
        'isdefault'     => 0
    );
        
     $gid = $db->insert_query("settinggroups", $setting_group); 
        
    $setting_array = array(
        'relations_type' => array(
            'title' => 'Beziehungskategorien',
            'description' => 'Welche Kategorien sind möglich für das Beziehungssystem?',
            'optionscode' => 'text',
            'value' => 'Freundschaften, Liebschaften, Feindschaften, Bekanntschaften, Vergangenheit, Sonstiges', // Default
            'disporder' => 1
        ),
        'relations_avatar' => array(
            'title' => 'Standard-Avatar',
            'description' => 'Wie heißt die Bilddatei, für die Standard-Avatare? Dieser Avatar wird für die NPCs eingetragen und falls der entsprechende Charakter noch kein Avatar hochgeladen hat. Damit der Avatar für jedes Design angepasst wird, sollte der Namen in allen Designs gleich sein.',
            'optionscode' => 'text',
            'value' => 'default_avatar.png', // Default
            'disporder' => 2
        ),
        'relations_avatar_guest' => array(
            'title' => 'Avatar ausblenden',
            'description' => 'Sollen die Avatare für Gäste ausgeblendet werden under der angegebene Standard-Avatar angezeigt werden?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 3
        ),
        'relations_accepted' => array(
            'title' => 'Eintragungen aktzeptieren',
            'description' => 'Müssen Beziehungseintragungen erst bestätigt werden von dem beanfragten Usern?',
            'optionscode' => 'yesno',
            'value' => '2', // Default
            'disporder' => 4
        ),
        'relations_npc' => array(
            'title' => 'NPCs',
            'description' => 'Dürfen die User auch NPCs hinzufügen?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 5
            ),
        'relations_npc_home' => array(
            'title' => 'Heimatstatus-Möglichkeiten',
            'description' => 'Welche Möglichkeiten soll bei den NPCs für den Heimatstatus geben?',
            'optionscode' => 'text',
            'value' => 'Urgestein, Einwohner, Heimkehrer, Neulinge', // Default
            'disporder' => 6
            ),
        'relations_npc_relation' => array(
            'title' => 'Beziehungsstatus-Möglichkeiten',
            'description' => 'Welche Möglichkeiten soll bei den NPCs für den Beziehungsstatus geben?',
            'optionscode' => 'text',
            'value' => 'Single, Verliebt, Vergeben, Offene Beziehung, Verlobt, Verheiratet, Getrennt, Geschieden, Verwitwet, Es ist kompliziert', // Default
            'disporder' => 7
             ),
    );
        
        foreach($setting_array as $name => $setting)
        {
            $setting['name'] = $name;
            $setting['gid']  = $gid;
            $db->insert_query('settings', $setting);
        }
    
        rebuild_settings();

    // TEMPLATES ERSTELLEN

    // Beziehungsanzeige im Profil
    $insert_array = array(
        'title'		=> 'relations',
        'template'	=> $db->escape_string('<table border="0" cellspacing="0" cellpadding="5" class="tborder">
        <tr>
            <td class="thead">
                <strong>Beziehungen</strong>
            </td>
        </tr>
        <tr>
            <td class="trow1" align="center">
                {$relations_type}
            </td>
        </tr>
    </table>
    <br />'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);
    
    // Beziehung hinzufügen
    $insert_array = array(
        'title'		=> 'relations_add',
        'template'	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder tfixed">
        <tr>
            <td class="thead">Zu deinen Beziehungen hinzufügen</td>
        </tr>
        <tr>
            <td align="center">	
                <form id="add_relation" method="post" action="member.php?action=profile&uid={$memprofile[\'uid\']}">
                    <table style="width: 100%; margin: auto; margin-top: 10px;">                         
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" class="textbox" name="relationship" id="relationship" placeholder="Kurzes Schlagwort über die Beziehung der beiden Charaktere?" style="width: 98%;height: 17px;margin-bottom: 0;" required>   
                                </td>                       
                                <td>
                                    <select name=\'type\' id=\'type\' style="width: 100%;" required>       
                                        <option value="">Kategorie wählen</option>
                                        {$cat_select}	
                                    </select>		
                                </td>                                                    
                            </tr>
                            <tr>
                                <td colspan="2"><textarea name="description" id="description" class="textfield" placeholder="Ausführliche Beschreibung der Beziehung der beidne Charaktere?" style="width: 100%; height: 100px"  required></textarea></td>
                            </tr>			
                        </tbody>
                    </table>
                    <br>
                    <div style="width: 145px; margin: auto;"> 
                        <input type="submit" name="add_relation" id="submit" class="button">
                    </div>                                  
                </form>
            </td>
        </tr>
    </table>
    <br />'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // NPC hinzufügen
    $insert_array = array(
        'title'		=> 'relations_add_npc',
        'template'	=> $db->escape_string('<table border="0" cellspacing="" cellpadding="" class="tborder tfixed">
        <tbody>
            <tr>
                <td class="thead">NPC hinzufügen</td>
            </tr>
        </tbody>
    </table>
    <center>		
        <form id="add_npc" method="post" action="member.php?action=profile&uid={$memprofile[\'uid\']}">
            <table cellpadding="0" cellspacing="4" border="0" width="32%">	
                <tbody>
                    <tr>
                        <td align="right">
                            <input type="text" class="textbox" name="npc_name" id="npc_name" placeholder="Vorname Nachname" style="width: 335px;" required>
                            <input type="text" class="textbox" name="npc_age" id="npc_age" placeholder="XXX Jahre" style="width:335px;" required>
                            <select name=\'npc_home\' id=\'npc_home\' style="width: 100%;" required>
                                <option value="">Heimatstatus wählen</option>
                                {$home_select}	
                                <option value="Lebt nicht in Barton Hills">Lebt nicht in Barton Hills</option>
                            </select>
                            <select name=\'npc_relation\' id=\'npc_relation\' style="width: 100%;" required>
                                <option value="">Beziehungsstatus wählen</option>
                                {$relation_select}	
                            </select>
                            <input type="text" class="textbox" name="relationship" id="relationship" placeholder="Art der Beziehung (z.B. Mutter)" style="width: 335px;margin-bottom: 0;padding: 3px;" required>
                            <select name=\'type\' id=\'type\' style="width: 100%;" required>       		
                                <option value="">Kategorie wählen</option>		
                                {$cat_select}			
                            </select>      
                        </td>
                        <td>
                            <textarea name="description" id="description" class="textfield" style="min-width:350px; min-height: 162px;" required></textarea>
                        </td>    
                    </tr>
                                         
                    <tr>
                        <td valign="bottom" align="center" colspan="2">
                            <input type="text" class="textbox" name="npc_search" id="npc_search" placeholder="Gibt es ein Gesuch zu diesem NPC? Hier den Gesuchslink bitte angeben" style="width: 99%;margin-bottom: 0;padding: 3px;">
                        </td>    
                    </tr>
                                            
                    <tr>
                        <td valign="bottom" align="center" colspan="2">
                            <input type="submit" name="add_npc" id="submit" class="button">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </center>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Index-Benachrichtigung
    $insert_array = array(
        'title'		=> 'relations_alert',
        'template'	=> $db->escape_string('<div class="pm_alert">
       <a href="usercp.php?action=relations"><strong>Du hast eine neue Beziehungsanfrage! Du musst sie noch bestätigen oder ablehnen</strong></a>
    </div>
    <br />'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Einzelne Beziehung
    $insert_array = array(
        'title'		=> 'relations_bit',
        'template'	=> $db->escape_string('<div id="relation">
        <div class="name">{$username}</div>
        <div class="beziehung">{$relationship}</div>
        <div style="display: flex; flex-wrap: wrap; margin: auto;">
            <div class="infos">
                {$useravatar}
                <div class="fact">{$age} Jahre</div>
                <div class="fact">{$relation[\'fid16\']}</div>
                {$option}
            </div>
            <div class="text">
                {$description}
            </div>
        </div>	 
    </div>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Einzelne NPC Beziehung
    $insert_array = array(
        'title'		=> 'relations_bit_npc',
        'template'	=> $db->escape_string('<div id="relation">
        <div class="name">[NPC] {$npc_name} <span style="float:right;padding:2px">{$npc_search}</span></div>
        <div class="beziehung">{$relationship}</div>
        <div style="display: flex; flex-wrap: wrap; margin: auto;">
            <div class="infos">
                {$npc_avatar}
                <div class="fact">{$npc_age}</div>
                <div class="fact">{$npc_relation}</div>
                {$option}
            </div>
            <div class="text">
                {$description}
            </div>
        </div>	 
    </div>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Bearbeitung im Profil
    $insert_array = array(
        'title'		=> 'relations_edit',
        'template'	=> $db->escape_string('<a href="#popinfo$rid"># Bearbeiten</a>

        <div id="popinfo$rid" class="relationspop">            
            <div class="pop">
                <form action="member.php?action=profile&uid={$memprofile[\'uid\']}" method="post">
                    <input type="hidden" name="rid" id="rid" value="{$rid}" />	
                    <table style="width: 100%; margin: auto; margin-top: 10px;">                         	
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" class="textbox" name="relationship" id="relationship" value="{$relationship}" style="width: 98%;height: 17px;margin-bottom: 0;" required> 
                                </td>                       
                                <td>
                                    <select name=\'type\' id=\'type\' style="width: 100%;" required>       
                                        <option value="{$type}">{$type}</option>
                                        {$cat_select}	
                                    </select>		
                                </td>                                                    
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <textarea name="description" id="description" class="textfield" style="width: 100%; height: 100px"  required>{$description}</textarea>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" align="center">
                                    <select name="relation_edit" id="relation_edit">
                                        <option>Private Nachricht über die Veränderung schicken?</option>
                                        <option value="ja">Ja, verschicke eine Nachricht</option>
                                        <option value="nein">Nein, verschicke keine Nachricht</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" align="center">
                                    <input type="submit" name="edit_relation" id="submit" class="button" value="Relation editieren">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
            <a href="#closepop" class="closepop"></a>    
        </div>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Bearbeitung NPC
    $insert_array = array(
        'title'		=> 'relations_edit_npc',
        'template'	=> $db->escape_string('<a href="#popinfo$rid">Bearbeiten</a>

        <div id="popinfo$rid" class="relationspop">            
            <div class="pop">
                <form action="member.php?action=profile&uid={$memprofile[\'uid\']}" method="post">
                    <input type="hidden" name="rid" id="rid" value="{$rid}" />	
                    <table cellpadding="0" cellspacing="4" border="0" width="32%">	
                        <tbody>
                            <tr>
                                <td align="right">
                                    <input type="text" class="textbox" name="npc_name" id="npc_name" value="{$relation[\'npc_name\']}" style="width: 335px;" required>
                                    <input type="text" class="textbox" name="npc_age" id="npc_age" value="{$npc_age}" style="width:335px;" required>
                                    <select name=\'npc_home\' id=\'npc_home\' style="width: 100%;" required>
                                        <option value="{$npc_home}">{$npc_home}</option>
                                        {$home_select}	
                                        <option value="Lebt nicht in Barton Hills">Lebt nicht in Barton Hills</option>
                                    </select>
                                    <select name=\'npc_relation\' id=\'npc_relation\' style="width: 100%;" required>
                                        <option value="{$npc_relation}">{$npc_relation}</option>
                                        {$relation_select}	
                                    </select>
                                    <input type="text" class="textbox" name="relationship" id="relationship" value="{$relationship}" style="width: 335px;margin-bottom: 0;padding: 3px;" required>
                                    <select name=\'type\' id=\'type\' style="width: 100%;" required>       		
                                        <option value="{$type}">{$type}</option>		
                                        {$cat_select}			
                                    </select>      
                                </td>
                                <td>
                                    <textarea name="description" id="description" class="textfield" style="min-width:350px; min-height: 162px;" required>{$description}</textarea>
                                </td>    
                            </tr>
                                             
                        <tr>
                            <td valign="bottom" align="center" colspan="2">
                                <input type="text" class="textbox" name="npc_search" id="npc_search" value="{$relation[\'npc_search\']}" style="width: 99%;margin-bottom: 0;padding: 3px;">
                            </td>    
                        </tr>
                                                
                        <tr>
                            <td valign="bottom" align="center" colspan="2">
                                <input type="submit" name="edit_npc" id="submit" class="button" value="NPC editieren">
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </form>
            </div>
            <a href="#closepop" class="closepop"></a>    
        </div>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Keine Beziehung innerhalb der Kategorie
    $insert_array = array(
        'title'		=> 'relations_none',
        'template'	=> $db->escape_string('<div class="trow2">
        <table border="0" cellpadding="5" cellspacing="5" class="smalltext">
            <tr>
                <td>
                    <div style="text-align:center;margin:10px auto;">Keine Beziehung innerhalb dieser Kategorie vorhanden!</div>
                </td>
            </tr>
        </table>
    </div>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Anzeige der Kategorien
    $insert_array = array(
        'title'		=> 'relations_type',
        'template'	=> $db->escape_string('<div style="width: 48.9%; float: left; margin: 5px;">
        <div class="tcat">{$typ}</div>
        <div style="height: 220px; overflow: auto;">
            {$relations_none}
            {$relations_bit}
        </div>
    </div>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Usercp
    $insert_array = array(
        'title'		=> 'relations_usercp',
        'template'	=> $db->escape_string('<html>	
        <head>
            <title>Verwaltung der Beziehungsanfragen</title>
            {$headerinclude}	
        </head>	
        <body>
            {$header}
            <table width="100%" border="0" align="center">
                <tr>
                    {$usercpnav}
                    <td valign="top">
                        <table border="0" cellspacing="0" cellpadding="5" class="tborder">
                            <tbody>
                                <tr>
                                    <td class="thead" colspan="1">
                                        <strong>erhaltenen Anfragen</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td valign="top">
                                        <table width="100%" class="trow1">									
                                            <tr>										
                                                <td class="thead" width="16%">Angefragt von</td>					
                                                <td class="thead" width="16%">Anfrage für</td>										
                                                <td class="thead" width="16%">Kategorie</td>										
                                                <td class="thead" width="16%">Beziehung</td>										
                                                <td class="thead" width="16%">Beschreibung</td>									
                                                <td class="thead" width="16%">Optionen</td>									
                                            </tr>
                                        {$relations_inquiry_bit}
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="thead" colspan="1"><strong>Eigene ausstehenden Anfragen</strong></td>
                                </tr>
                                <tr>
                                    <td valign="top">
                                        <table width="100%" class="trow1">									
                                            <tr>										
                                                <td class="thead" width="16%">Angefragt bei</td>					
                                                <td class="thead" width="16%">Angefragt für</td>										
                                                <td class="thead" width="16%">Kategorie</td>										
                                                <td class="thead" width="16%">Beziehung</td>										
                                                <td class="thead" width="16%">Beschreibung</td>									
                                                <td class="thead" width="16%">Optionen</td>								
                                            </tr>
                                        {$relations_own_inquiry_bit}
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>
            {$footer}	
        </body>		
    </html>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Bearbeiten Usercp
    $insert_array = array(
        'title'		=> 'relations_usercp_edit',
        'template'	=> $db->escape_string('<i class="fas fa-check"></i> <a href="#popinfo$rid">Anfrage bearbeiten</a>

        <div id="popinfo$rid" class="relationspop">            
            <div class="pop">
                <form action="usercp.php?action=relations" method="post">
                    <input type="hidden" name="rid" id="rid" value="{$rid}" />	
                    <table style="width: 100%; margin: auto; margin-top: 10px;">                         	
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" class="textbox" name="relationship" id="relationship" value="{$relationship}" style="width: 98%;height: 17px;margin-bottom: 0;" required> 
                                </td>                       
                                <td>
                                    <select name=\'type\' id=\'type\' style="width: 100%;" required>       
                                        <option value="{$type}">{$type}</option>
                                        {$cat_select}	
                                    </select>		
                                </td>                                                    
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <textarea name="description" id="description" class="textfield" style="width: 100%; height: 100px"  required>{$description}</textarea>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" align="center">
                                    <select name="relation_edit" id="relation_edit">
                                        <option>Private Nachricht über die Veränderung schicken?</option>
                                        <option value="ja">Ja, verschicke eine Nachricht</option>
                                        <option value="nein">Nein, verschicke keine Nachricht</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" align="center">
                                    <input type="submit" name="edit_relation" id="submit" class="button" value="Relation editieren">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
            <a href="#closepop" class="closepop"></a>    
        </div>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Usercp bit 
    $insert_array = array(
        'title'		=> 'relations_usercp_inquiry_bit',
        'template'	=> $db->escape_string('<tr align="center">
        <td class="trow1">
            {$relation_from}
        </td>
        <td class="trow1">
            {$relation_for}
        </td>
        <td class="trow1">
            {$type}
        </td>
        <td class="trow1">
            {$relationship}
        </td>
        <td class="trow1">
            <div style="max-height: 100px;overflow: auto;text-align: justify;padding-right:3px">{$description} </div>    
        </td>  
        <td class="trow1">
            {$option}       
        </td>    
    </tr>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Ablehnungs-Popfenster 
    $insert_array = array(
        'title'		=> 'relations_usercp_reject',
        'template'	=> $db->escape_string('<br>
        <i class="fas fa-times"></i> <a href="#popinfo$rid">Anfrage ablehnen</a>
        
                    <div id="popinfo$rid" class="relationspop">
                        <div class="pop">
                            <form method="post" name="reason">
                                <input type="hidden" name="reject" value="{$rid}" />
                                <input type="hidden" name="action" value="relations" />
                                <table width="100%">
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="tcat">Ablehnungsgrund</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <textarea name="reason" id="reason"  style="width: 99%;height: 60px;" placeholder="Schreibe deinen ausführlichen Ablehnungsgrund hier auf, damit dein Gegenüber eine Begründung per PN zugeschickt bekommt!"></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <input type="submit" value="Beziehungsanfrage ablehnen" class="button">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </form>		
                        </div>
                        <a href="#closepop" class="closepop"></a>
                    </div>'),
        'sid'		=> '-1',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function relations_is_installed()
{
    global $db, $cache, $mybb;
  
      if($db->table_exists("relations"))  {
        return true;
      }
        return false;
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function relations_uninstall()
{
    global $db;

    //DATENBANK LÖSCHEN
    if($db->table_exists("relations"))
    {
        $db->drop_table("relations");
    }

    // SPALTE LÖSCHEN
    if($db->field_exists("relations_pn", "users"))
	{
		$db->drop_column("users", "relations_pn");
	}
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'relations%'");
    $db->delete_query('settinggroups', "name = 'relations'");

    rebuild_settings();

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE '%relations%'");
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function relations_activate()
{
    global $db, $post, $cache;
    
    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // MyALERTS STUFF
    if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

        // Alert beim hinzufügen
		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('relations_new'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);

        // Alert beim bearbeiten
        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('relations_alert_edit'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);

        // Alert beim löschen
        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('relations_delete'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);
    }
    
    // VARIABLEN EINFÜGEN
	find_replace_templatesets('header', '#'.preg_quote('{$bbclosedwarning}').'#', '{$new_relations_alert} {$bbclosedwarning}');
    find_replace_templatesets('member_profile', '#'.preg_quote('{$contact_details}').'#', '{$contact_details} {$relations_memprofile} {$relations_add}');

    // STYLESHEET HINZUFÜGEN
    $css = array(
		'name' => 'relations.css',
		'tid' => 1,
		'attachedto' => '',
		"stylesheet" =>	'/* POPFENSTER */

        .relationspop {
            position:fixed;
            top:0;
            right:0;
            bottom:0;
            left:0;
            background:hsla(0,0%,0%,0.3);
            z-index: 99;
            opacity:0;
            -webkit-transition:.5s ease-in-out;
            -moz-transition:.5s ease-in-out;
            transition:.5s ease-in-out;
            pointer-events:none;
        }
        
        .relationspop:target {
            opacity:1;
            pointer-events: auto;
        }
        
        /* Hier wird das Popup definiert! */
        .relationspop>.pop {
            position:relative;
            margin:10% auto;
            width:600px;
            max-height:450px;
            box-sizing:border-box;
            padding:10px;
            background: #4C6173;
            border: 3px solid #8596A6;
            text-align:justify;
            overflow:auto;
            z-index:999;
            font-size: 10px;
            line-height: 15px;
            text-align: justify;
            letter-spacing: 1px;
            color: #C7CFD9;
            font-family: Overpass,sans-serif;
        }
        
        .relationspop>.closepop {
            position:absolute;
            right:-5px;
            top:-5px;
            width:100%;
            height:100%;
            z-index: 1;
        }
        
        /*ANSICHT PROFIL*/
        #relation {
            padding: 5px;
            margin-bottom: 5px;
        }
        
        #Basic #relation .name {
            font-size: 15px;
            font-weight: 600;
            text-align: left;
            text-transform: uppercase;
            color: #8596A6;
            border-bottom: #8596a6 2px solid;
            margin: 0;
            font-family: Playfair Display,serif;
            line-height: 22px;
        }
        
        #relation .name i {
            font-size: 15px;
            padding: 2px;
        }
        
        #relation .beziehung {
            font-family: Overpass,sans-serif;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 2px 0;
        }
        
        #relation .avatar {
            width: 90px;
            background: #8596A6;
            border: 3px solid #8596A6;
        }
        
        #relation .fact {
            padding: 2px 5px;
            font-family: Overpass,sans-serif;
            font-size: 8px;
            margin: 3px 0px;
            font-weight: 600;
            line-height: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #8596A6;
            text-align: center;
            background: #293340;
        }
        
        #relation .fact a:link, #relation .fact a:visited, #relation .fact a:active, #relation .fact a:hover {
            color: #8596A6;
        }
        
        
        #relation .text b {
            font-weight: 700;
            color: #191F26;
            text-transform: uppercase;
        }
        
        #relation .text i {
            color: #8596A6;
            font-style: italic;
        }
        
        #relation .infos {
            float: left;
            width: 130px;
            margin-right: 5px;
        }
        
        #relation .text {
            width: 295px;
            text-align: justify;
            padding: 5px;
            height: 150px;
            float: right;
            font-size: 11px;
            overflow: auto;
        }',
		'cachefile' => $db->escape_string(str_replace('/', '', 'relations.css')),
		'lastmodified' => time()
	);
    
    $sid = $db->insert_query("themestylesheets", $css);
	$db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=".$sid), "sid = '".$sid."'", 1);

	$tids = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function relations_deactivate()
{
    global $db, $cache;

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
    find_replace_templatesets("header", "#".preg_quote('{$new_relations_alert}')."#i", '', 0);
    find_replace_templatesets("member_profile", "#".preg_quote('{$relations_memprofile} {$relations_add}')."#i", '', 0);

    // STYLESHEET ENTFERNEN
	$db->delete_query("themestylesheets", "name = 'relations.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}

    // MyALERT STUFF
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertTypeManager->deleteByCode('relations_new');
        $alertTypeManager->deleteByCode('relations_alert_edit');
        $alertTypeManager->deleteByCode('relations_delete');
	}
}

// FUNKTIONEN - THE MAGIC
function relations_member_profile_end()
{
    global $db, $mybb, $memprofile, $templates, $theme, $cat_select, $home_select, $relation_select, $relations_add, $relations_show, $relations_type, $relations_bit, $option, $relations_accepted_setting;

    // HTML & BBC ERLAUBEN/DARSTELLEN
    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser;

    $options = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );

    // IDs HOLEN
    // man selbst
    $relation_user = $mybb->user['uid'];
    // wen man hinzufügen will
    $relation_profile = $memprofile['uid'];

    // EINSTELLUNGEN HOLEN
    $relations_type_setting = $mybb->settings['relations_type'];
    $relations_avatar_setting = $mybb->settings['relations_avatar'];
    $relations_avatar_guest_setting = $mybb->settings['relations_avatar_guest'];
    $relations_accepted_setting = $mybb->settings['relations_accepted'];
    $relations_npc_setting = $mybb->settings['relations_npc'];
    $relations_npc_home_setting = $mybb->settings['relations_npc_home'];
    $relations_npc_relation_setting = $mybb->settings['relations_npc_relation'];

    // AUSWAHLMÖGLICHKEIT DROPBOX GENERIEREN
    // Kategorien
    $relations_cat = explode (", ", $relations_type_setting);
    foreach ($relations_cat as $cat) {
        $cat_select .= "<option value='{$cat}'>{$cat}</option>";
    }
    // Heimatstatus NPCs
    $relations_home = explode (", ", $relations_npc_home_setting);
    foreach ($relations_home as $home_npc) {
        $home_select .= "<option value='{$home_npc}'>{$home_npc}</option>";
    }
    // Beziehungsstatus NPCs
    $relations_relation = explode (", ", $relations_npc_relation_setting);
    foreach ($relations_relation as $relation_npc) {
        $relation_select .= "<option value='{$relation_npc}'>{$relation_npc}</option>";
    }

    // GÄSTE DÜRFEN GAR NICHT HINZUFÜGEN
    if($mybb->user['uid'] != '0'){
        // Man kann sich nicht selbst hinzufügen
        if($memprofile['uid'] != $mybb->user['uid']){
            eval("\$relations_add = \"" . $templates->get ("relations_add") . "\";");
        } 
        // Wenn NPCs erlaubt sind, dann stattdessen das NPC Formular anzeigen
        elseif ($relations_npc_setting == '1') {
            eval("\$relations_add = \"" . $templates->get ("relations_add_npc") . "\";");
        }
        else {
            $relations_add = "";
        }
    }

    // EINTRAGEN VON VORHANDENEN ACCOUNTS   
    if(isset($_POST['add_relation'])) {

        // Relation müssen nicht bestätigt werden
        if($relations_accepted_setting == '0'){
            $accepted = 1;
        } else {
            $accepted = 0;
        }
    
        $new_relation = array(
            "relation_by" => $relation_user,
            "relation_with" => $relation_profile,
            "type" => $db->escape_string($mybb->get_input('type')),
            "relationship" => $db->escape_string($mybb->get_input('relationship')),
            "description" => $db->escape_string($mybb->get_input('description')),
            "npc_name" => $db->escape_string($mybb->get_input('npc_name')),
            "npc_age" => $db->escape_string($mybb->get_input('npc_age')),
            "npc_home" => $db->escape_string($mybb->get_input('npc_home')),
            "npc_relation" => $db->escape_string($mybb->get_input('npc_relation')),
            "npc_search" => $db->escape_string($mybb->get_input('npc_search')),
            "accepted" => $accepted,
        );

        // MyALERTS STUFF
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $user = get_user($memprofile['uid']);
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relations_new');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$memprofile['uid'], $alertType, (int)$relation_user);
                $alert->setExtraDetails([
                    'username' => $user['username']
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }

        $db->insert_query("relations", $new_relation);
        redirect("member.php?action=profile&uid={$relation_user}", "Die neue Beziehung wurde erfolgreich zu deiner Beziehungskiste hinzugefügt. Gegebenfalls muss diese noch bestätigt werden!");
    }

    // NPC EINTRAGEN     
    if(isset($_POST['add_npc'])) {

        $new_npc = array(
        "relation_by" => $relation_user,
        "relation_with" => "0",
        "type" => $db->escape_string($mybb->get_input('type')),
        "relationship" => $db->escape_string($mybb->get_input('relationship')),
        "description" => $db->escape_string($mybb->get_input('description')),
        "npc_name" => $db->escape_string($mybb->get_input('npc_name')),
        "npc_age" => $db->escape_string($mybb->get_input('npc_age')),
        "npc_home" => $db->escape_string($mybb->get_input('npc_home')),
        "npc_relation" => $db->escape_string($mybb->get_input('npc_relation')),
        "npc_search" => $db->escape_string($mybb->get_input('npc_search')),
        "accepted" => "1",
        );

        $db->insert_query("relations", $new_npc);
        redirect("member.php?action=profile&id={$relation_user}", "Der neue NPC wurde erfolgreich zu deiner Beziehungskiste hinzugefügt.");
    }

    // IM PROFIL DIE RELATION ANZEIGEN LASSEN

    foreach ($relations_cat as $typ) {
        $relations_bit = "";

        // Wenn keine Relation in dieser Kategorie eingetragen wurde
        eval("\$relations_none = \"".$templates->get("relations_none")."\";");

        // Abfrage der Datenbank RELATIONS + USER + USERFIELDS
        $relations_query = $db->query("SELECT * FROM ".TABLE_PREFIX."relations r
        LEFT JOIN ".TABLE_PREFIX."users u
        ON u.uid = r.relation_with
        LEFT JOIN ".TABLE_PREFIX."userfields uf
        ON uf.ufid = r.relation_with
        WHERE type = '$typ'
        AND relation_by = '$relation_profile'
        AND accepted = '1' 
        ORDER BY username ASC, npc_name ASC
        ");

        while ($relation = $db->fetch_array ($relations_query)) {
            $relations_none = "";

            // ALLES LEER LAUFEN LASSEN
            $rid = "";
            $relation_by = "";
            $relation_with = "";
            $type = "";
            $relationship = "";
            $description = "";
            $npc_name = "";
            $npc_age = "";
            $npc_home = "";
            $npc_relation = "";
            $npc_search = "";

            // MIT INFOS FÜLLEN
            $rid = $relation['rid'];
            $relation_by = $relation['relation_by'];
            $relation_with = $relation['relation_with'];
            $type = $relation['type'];
            $relationship = $relation['relationship'];
            $description = $relation['description'];
            $npc_age = $relation['npc_age'];
            $npc_home = $relation['npc_home'];
            $npc_relation = $relation['npc_relation'];

            // NPCs
            if ($relation_with == 0){

                // Avatar bilden
                $npc_avatar = "<img src='{$theme['imgdir']}/{$relations_avatar_setting}' class='avatar'>";

                // Link zum Gesuch bilden	
			    if(!empty($relation['npc_search'])){
                    $npc_search = "<a href=\"{$relation['npc_search']}\" original-title=\"Zum Gesuch\"><i class=\"fas fa-search\"></i></a>";
                } else {
                    $npc_search = "";
                }

                // FARBIGE BENUTZERNAMEN
				if ($npc_home == 'Urgestein') {
					$npc_name = "<span class=\"urgestein\">{$relation['npc_name']}</span>";  
                }
				elseif ($npc_home == 'Heimkehrer') {
					$npc_name = "<span class=\"rueckkehrer\">{$relation['npc_name']}</span>";
				}
				elseif ($npc_home == 'Einwohner') {
					$npc_name = "<span class=\"zugezogene\">{$relation['npc_name']}</span>";
				}
				elseif ($npc_home == 'Neuling') {
					$npc_name = "<span class=\"neulinge\">{$relation['npc_name']}</span>";
				}
				else {
					$npc_name = $relation['npc_name'];
				}  

                // LÖSCHEN UND BEARBEITEN VON NPCS
				if($relation_user == $relation_by){
                    eval("\$edit_npc = \"" . $templates->get("relations_edit_npc") . "\";");
                    $option = "<div class=\"fact\"><a href=\"member.php?action=profile&delrel={$rid}\">Löschen</a> # {$edit_npc}</div>";
                 }
                 else {
                     $option = "";
                 }
            
                eval("\$relations_bit .= \"" . $templates->get ("relations_bit_npc") . "\";");
            } 
            // NORMALE USER
            else {

                // FARBIGE USERNAME 
                $profilelink = format_name($relation['username'], $relation['usergroup'], $relation['displaygroup']);
                $username = build_profile_link($profilelink, $relation['relation_with']);

 // AUTOMATISCHES ALTER
  $all_months = $mybb->settings['inplaykalender_months'];
    $year = $mybb->settings['inplaykalender_year'];
    //wir wollen nur den letzten Monat

    $month = strrpos($all_months, ',')+1;
    $month = substr($all_months, $month); //$last_word = PHP.

    $monatsnamen = array(
        1 => "Januar 2021",
        2 => "Februar 2021",
        3 => "März",
        4 => "April",
        5 => "Mai",
        6 => "Juni",
        7 => "Juli",
        8 => "August",
        9 => "September",
        10 => "Oktober",
        11 => "November",
        12 => "Dezember 2020"
    );

    //wir wollen den Namen zu einer Zahl umwandeln
    $month_int = array_search($month, $monatsnamen);

    //Jetzt wollen wir eine Variable im Datumsformat
    $ingame = new DateTime("01-" . $month_int . "-" . $year);
    //Geburtstag des Users bekommen
    $gebu = $db->query("SELECT birthday FROM mybb_users WHERE uid = $relation_with");
    while ($data = $db->fetch_array($gebu)) {
        //datumsformat:  
        $geburtstag = new DateTime($data['birthday']);
    }
    $interval = $ingame->diff($geburtstag);
    $age = $interval->format("%Y");

                // AVATARE
                // Einstellung für Gäste Avatare ausblenden
                if ($relations_avatar_guest_setting == 1){
                    // Gäste und kein Avatar - Standard-Avatar
                    if ($mybb->user['uid'] == '0' || $relation['avatar'] == '') {
                        $useravatar  = "<img src='{$theme['imgdir']}/{$relations_avatar_setting}' class='avatar'>";
                    } else {
                        $useravatar  = "<img src='{$relation['avatar']}' class='avatar'>";
                    }

                } else {
                    // kein Avatar - Standard-Avatar
                    if ($relation['avatar'] == '') {
                        $useravatar  = "<img src='{$theme['imgdir']}/{$relations_avatar_setting}' class='avatar'>";
                    } else {
                        $useravatar  = "<img src='{$relation['avatar']}' class='avatar'>";
                    }
                }

                

                // BEARBEITEN - sieht nur der ersteller
                if($relation_user == $relation_by){
                eval("\$edit = \"" . $templates->get("relations_edit") . "\";");
                } else {
                    $edit = "";
                }
               
                // OPTIONEN - BUTTON 
				if($relation_user == $relation_by OR $relation_user == $relation_with){
                    $option = "<div class=\"fact\"><a href=\"member.php?action=profile&delrel={$rid}\">Löschen</a> {$edit}</div>";
                 }
                 else {
                     $option = "";
                 }

                eval("\$relations_bit .= \"" . $templates->get ("relations_bit") . "\";");
            }
            
        }

        // Die verschiedenen Kategorien auslesen lassen
        eval("\$relations_type .= \"" . $templates->get ("relations_type") . "\";");
    }

    // RELATIONS BEARBEITEN //TODO Alert verschicken bei der Bearbeitung
    $edit_rel = $mybb->input['edit_relation'];
	if (isset($mybb->input['edit_relation'])) {
		$rid = $mybb->input['rid'];

		$relation_edit = array(
            "type" => $db->escape_string($mybb->get_input('type')),
            "relationship" => $db->escape_string($mybb->get_input('relationship')),
            "description" => $db->escape_string($mybb->get_input('description')),
		);

        // MyALERTS STUFF
    $query_alert_edit = $db->simple_select("relations", "*", "rid = '{$edit_rel}'");
    while ($alert_edit = $db->fetch_array ($query_alert_edit)) {
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $user = get_user($alert_edit['relation_with']);
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relations_alert_edit');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$alert_edit['relation_with'], $alertType, (int)$edit_rel);
                $alert->setExtraDetails([
                    'username' => $user['username']
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }
    }

		$db->update_query("relations", $relation_edit, "rid='{$rid}'");
		redirect("member.php?action=profile&uid={$relation_user}", "Du hast den Beziehungseintrag erfolgreich bearbeitet und wirst nun zurück auf dein Profil geleitet!");
	}

    // NPCs BEARBEITEN
	if (isset($mybb->input['edit_npc'])) {
		$rid = $mybb->input['rid'];

		$npc_edit = array(
            "type" => $db->escape_string($mybb->get_input('type')),
            "relationship" => $db->escape_string($mybb->get_input('relationship')),
            "description" => $db->escape_string($mybb->get_input('description')),
            "npc_name" => $db->escape_string($mybb->get_input('npc_name')),
            "npc_age" => $db->escape_string($mybb->get_input('npc_age')),
            "npc_home" => $db->escape_string($mybb->get_input('npc_home')),
            "npc_relation" => $db->escape_string($mybb->get_input('npc_relation')),
            "npc_search" => $db->escape_string($mybb->get_input('npc_search')),
		);

		$db->update_query("relations", $npc_edit, "rid='{$rid}'");
		redirect("member.php?action=profile&uid={$relation_user}", "Du hast den NPC erfolgreich bearbeitet und wirst nun zurück auf dein Profil geleitet!");
	}

    // Relations löschen
	$delete = $mybb->input['delrel'];
	if($delete) {

    // MyALERTS STUFF
    $query_alert = $db->simple_select("relations", "*", "rid = '{$delete}'");
    while ($alert_del = $db->fetch_array ($query_alert)) {
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $user = get_user($alert['relation_with']);
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relations_delete');
             // WENN DER EINTRÄGER GELÖSCHT HAT
             if ($alertType != NULL && $alertType->getEnabled() && $mybb->user['uid'] != $alert_del['relation_with']) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$alert_del['relation_with'], $alertType, (int)$delete);
                $alert->setExtraDetails([
                    'username' => $user['username']
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            } // WENN DER EINGETRAGENE LÖSCHT
            elseif ($alertType != NULL && $alertType->getEnabled() && $mybb->user['uid'] == $alert_del['relation_with']) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$alert_del['relation_by'], $alertType, (int)$delete);
                $alert->setExtraDetails([
                    'username' => $user['username']
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }
    }

		$db->delete_query("relations", "rid = '$delete'");
		redirect("member.php?action=profile&uid={$relation_user}", "Du hast die Beziehungseintrag erfolgreich aus deiner Beziehungskiste gelöscht und wirst nun zurück auf dein Profil geleitet!");
	}


    // Im Profil anzeigen lassen
    eval("\$relations_show .= \"" . $templates->get ("relations") . "\";");

}

// ANFRAGEN VORHER BESTÄTIGEN MÜSSEN
function relations_usercp()
{
    global $mybb, $db, $templates, $lang, $header, $headerinclude, $footer, $page, $usercpnav, $relations_own_inquiry_bit, $relations_inquiry_bit;
    
    // DAS ACTION MENÜ
	$mybb->input['action'] = $mybb->get_input('action');

    if($mybb->input['action'] == "relations"){

        // NAVIGATION
        add_breadcrumb($lang->nav_usercp, "usercp.php");
        add_breadcrumb("Verwaltung der Beziehungsanfragen", "usercp.php?action=relations");

        // ID HOLEN
        // man selbst
        $relation_user = $mybb->user['uid'];

        // ERHALTENDE ANFRAGEN
        // Abfrage
        $inquiry_query = $db->query("SELECT * FROM ".TABLE_PREFIX."relations 
        WHERE relation_with = '$relation_user'
        AND accepted = '0' 
        ORDER BY relationship ASC
        ");

        // Auslese 
        while($inquery = $db->fetch_array($inquiry_query)) {

            // Leer laufen lassen
            $rid = "";
            $relation_by = "";
            $relation_with = "";
            $type = "";
            $relationship = "";
            $description = "";
            $username = "";

            // Mit Infos füllen
            $rid = $inquery['rid'];
            $relation_by = $inquery['relation_by'];
            $relation_with = $inquery['relation_with'];
            $type = $inquery['type'];
            $relationship = $inquery['relationship'];
            $description = $inquery['description'];

            // Anfrage von
            $from = $db->fetch_array($db->simple_select('users', '*', 'uid = ' . $relation_by));
            $profilelink_from = format_name($from['username'], $from['usergroup'], $from['displaygroup']);
            $relation_from = build_profile_link($profilelink_from, $from['relation_by']);

            // Anfrage für
            $for = $db->fetch_array($db->simple_select('users', '*', 'uid = ' . $relation_with));
            $profilelink_for = format_name($for['username'], $for['usergroup'], $for['displaygroup']);
            $relation_for = build_profile_link($profilelink_for, $for['relation_with']);

            // OPTIONEN - BUTTON 			
            eval("\$reject = \"" . $templates->get("relations_usercp_reject") . "\";");	
            $option = "<i class=\"fas fa-check\"></i> <a href=\"usercp.php?action=relations&accepted={$rid}\">Anfrage aktzeptieren</a> {$reject}";
            
            eval("\$relations_inquiry_bit .= \"".$templates->get("relations_usercp_inquiry_bit")."\";");	
        }

        // ERHALTENDE ANFRAGEN -> AUSZÄHLEN
		$countinquiry = $db->fetch_field($db->query("
        SELECT COUNT(accepted) AS notaccepted FROM ".TABLE_PREFIX."relations
        WHERE accepted = '0'
        AND relation_with = $relation_user
        "), "notaccepted");
        
        // Wenn keine Anfrage aussteht
		 if($countinquiry < '1'){
            $relations_inquiry_bit = "
            <tr>
                <td style=\"text-align:center;margin:10px auto;\" colspan=\"6\">Du hast aktuell keine ausstehenden Beziehungsanfragen!</td>
            </tr>";
        }


        // EIGENE AUSSTEHENDE ANFRAGEN
        // Abfrage
        $own_inquiry_query = $db->query("SELECT * FROM ".TABLE_PREFIX."relations r
        WHERE relation_by = '$relation_user'
        AND accepted = '0' 
        ORDER BY relationship ASC
        ");

        // Auslese 
        while($own = $db->fetch_array($own_inquiry_query)) {

            // Leer laufen lassen
            $rid = "";
            $relation_by = "";
            $relation_with = "";
            $type = "";
            $relationship = "";
            $description = "";

            // Mit Infos füllen
            $rid = $own['rid'];
            $relation_by = $own['relation_by'];
            $relation_with = $own['relation_with'];
            $type = $own['type'];
            $relationship = $own['relationship'];
            $description = $own['description'];
            
            // Anfrage von
            $from = $db->fetch_array($db->simple_select('users', '*', 'uid = ' . $relation_with));
            $profilelink_from = format_name($from['username'], $from['usergroup'], $from['displaygroup']);
            $relation_from = build_profile_link($profilelink_from, $from['relation_with']);

            // Anfrage für
            $for = $db->fetch_array($db->simple_select('users', '*', 'uid = ' . $relation_by));
            $profilelink_for = format_name($for['username'], $for['usergroup'], $for['displaygroup']);
            $relation_for = build_profile_link($profilelink_for, $for['relation_by']);

            // OPTIONEN - BUTTON 			
            eval("\$edit = \"" . $templates->get("relations_usercp_edit") . "\";");
            $option = "{$edit}<br>
            <i class=\"fas fa-trash\"></i> <a href=\"usercp.php?action=relations&delete={$rid}\">Anfrage löschen</a>";
            
            eval("\$relations_own_inquiry_bit .= \"".$templates->get("relations_usercp_inquiry_bit")."\";");	
        }


        // EIGENE AUSSTEHENDE ANFRAGEN -> AUSZÄHLEN
		$countowninquiry = $db->fetch_field($db->query("
        SELECT COUNT(accepted) AS notaccepted FROM ".TABLE_PREFIX."relations
        WHERE accepted = '0'
        AND relation_by = $relation_user
        "), "notaccepted");
        
        // Wenn keine Anfrage aussteht
		 if($countowninquiry < '1'){
            $relations_own_inquiry_bit = "
            <tr>
                <td style=\"text-align:center;margin:10px auto;\" colspan=\"6\">All deine Beziehungsanfragen wurden angenommen!</td>
            </tr>";
        }

        // OPTIONEN
        // Anfragen annehmen
        $accepted = $mybb->input['accepted'];
        if ($accepted) {

            $accepted_inquiry = array(
                "accepted" => "1",
            );
        
            $db->update_query("relations", $accepted_inquiry, "rid = '".$accepted."'");    
            redirect("usercp.php?action=relations","Du hast die Beziehungsanfrage erfolgreich angenommen und wirst nun zurückgeleitet!");
        }

        // Anfragen ablehnen
        $delete = $mybb->input['delete'];
        if($delete) {
            $db->delete_query("relations", "rid = '$delete'");
            redirect("usercp.php?action=relations", "Du hast deine Beziehungsanfrage erfolgreich gelöscht und wirst nun zurückgeleitet!");
        }

        // Anfrage bearbeiten
        if (isset($mybb->input['edit_relation'])) {
            $rid = $mybb->input['rid'];
    
            $relation_edit = array(
                "type" => $db->escape_string($mybb->get_input('type')),
                "relationship" => $db->escape_string($mybb->get_input('relationship')),
                "description" => $db->escape_string($mybb->get_input('description')),
            );
    
            $db->update_query("relations", $relation_edit, "rid='{$rid}'");
            redirect("usercp.php?action=relations", "Du hast die Beziehungsanfrage erfolgreich bearbeitet und wirst nun zurückgeleitet!");
        }

        // Anfrage ablehnen + Grund
        $reject = $mybb->get_input('reject');
        $reason = $mybb->get_input('reason');
        if($reject) {
            $query = $db->query("SELECT * FROM ".TABLE_PREFIX."relations WHERE rid = '$reject'");
            $rinquiry = $db->fetch_array($query);
            $ownuid = $mybb->user['uid'];
            $subject = "Ablehnung der Beziehungsanfrage";
            $message = "Ich musste deine Beziehungsanfrage leider ablehnen!
            Grund: ".$reason;
            $fromid = $ownuid;

            require_once MYBB_ROOT . "inc/datahandlers/pm.php";
            $pmhandler = new PMDataHandler();

            $pm = array(
                    "subject" => $subject,
                    "message" => $message,
                    "fromid" => $fromid,
                    "toid" => $rinquiry['relation_by']
            );

            $pmhandler->set_data($pm);

            // Now let the pm handler do all the hard work.
            if (!$pmhandler->validate_pm()) {
                    $pm_errors = $pmhandler->get_friendly_errors();
                    return $pm_errors;
            }
            else{
                    $pminfo = $pmhandler->insert_pm();
            }
            $db->delete_query("relations", "rid = '$reject'");
            redirect("usercp.php?action=relations", "Du hast die Beziehungsanfrage erfolgreich abgelehnt und wirst nun zurückgeleitet");
    }


        // das template für die ganze Seite 
        eval("\$page= \"".$templates->get("relations_usercp")."\";");   
        output_page($page);
    }

}

// INDEX-ALERT FÜR NEUE ANFRAGE
function relations_alert()
{
    global $db, $mybb, $templates, $new_relations_alert;

    // ID HOLEN
    // man selbst
    $relation_user = $mybb->user['uid'];

    $countnotaccepted = $db->fetch_field($db->query("
    SELECT COUNT(accepted) AS notaccepted FROM ".TABLE_PREFIX."relations
    WHERE accepted = '0'
    AND relation_with = $relation_user
    "), "notaccepted");

    if ($mybb->user['uid'] != 0) {
        if ($countnotaccepted > 0) {
            eval("\$new_relations_alert = \"" . $templates->get("relations_alert") . "\";");
        }
    }
}

// WAS PASSIERT MIT EINEM GELÖSCHTEN USER //TODO Funktioniert nicht!
function relations_user_delete()
{
    global $db, $cache, $mybb, $user;

    // EINTRAGUNGEN UPDATEN
    $update_other_relas = array(
        'relation_with' => 0,
        'npc_name' => $db->escape_string($user['username']),
    );

    //   $db->update_query("{name_of_table}", $update_array, "WHERE {options}");
    $db->update_query('relations', $update_other_relas, "relation_with='" . (int)$user['uid'] . "'");
    // löschen der eingetragenen Relas
    $db->delete_query('relations', "relation_by = " . (int)$user['uid'] . "");
}

function relations_myalert_alerts() {
	global $mybb, $lang;
	$lang->load('relations');

    // HINZUFÜGEN
    /**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_RelationsNewFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
			$alertContent = $alert->getExtraDetails();
            $userid = $db->fetch_field($db->simple_select("users", "uid", "username = '{$alertContent['username']}'"), "uid");
            $user = get_user($userid);
            $alertContent['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
	        return $this->lang->sprintf(
	            $this->lang->relations_new,
				$outputAlert['from_user'],
				$alertContent['username'],
	            $outputAlert['dateline']
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->relations) {
	            $this->lang->load('relations');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
            return $this->mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $alert->getObjectId();
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_RelationsNewFormatter($mybb, $lang, 'relations_new')
		);
    }


   // BEARBEITEN
	/**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_RelationsEditFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
			$alertContent = $alert->getExtraDetails();
            $userid = $db->fetch_field($db->simple_select("users", "uid", "username = '{$alertContent['username']}'"), "uid");
            $user = get_user($userid);
            $alertContent['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
	        return $this->lang->sprintf(
	            $this->lang->relations_alert_edit,
				$outputAlert['from_user'],
				$alertContent['username'],
	            $outputAlert['dateline']
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->relations) {
	            $this->lang->load('relations');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
	        return $this->mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $alert->getObjectId();
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_RelationsEditFormatter($mybb, $lang, 'relations_alert_edit')
		);
    }

    // LÖSCHEN
	/**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_RelationsDeleteFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
			$alertContent = $alert->getExtraDetails();
            $userid = $db->fetch_field($db->simple_select("users", "uid", "username = '{$alertContent['username']}'"), "uid");
            $user = get_user($userid);
            $alertContent['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
	        return $this->lang->sprintf(
	            $this->lang->relations_delete,
				$outputAlert['from_user'],
				$alertContent['username'],
	            $outputAlert['dateline']
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->relations) {
	            $this->lang->load('relations');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
            return $this->mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $alert->getObjectId();
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_RelationsDeleteFormatter($mybb, $lang, 'relations_delete')
		);
    }
    
}
