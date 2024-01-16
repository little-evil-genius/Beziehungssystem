<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
 
// HOOKS
// Profil - Hinzufügen und Ausgabe
$plugins->add_hook("member_profile_end", "relations_member_profile_end");
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
		"version"	=> "1.0.1",
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
		`npc_info` VARCHAR(500) NOT NULL,
		`npc_search` VARCHAR(1000) NOT NULL,
        PRIMARY KEY(`rid`),
        KEY `rid` (`rid`)
        )
        ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1
        ");

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
            'value' => 'Familie, Freundschaften, Liebschaften, Feindschaften, Bekanntschaften, Vergangenheit', // Default
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
        'relations_description' => array(
            'title' => 'Relationtexte',
            'description' => 'Dürfen User eine Beschreibung hinzufügen, damit die Relation mehr beschrieben wird?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 4
        ),
        'relations_npc' => array(
            'title' => 'NPCs',
            'description' => 'Dürfen die User auch NPCs hinzufügen?',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 5
            ),
    );
        
        foreach($setting_array as $name => $setting)
        {
            $setting['name'] = $name;
            $setting['gid']  = $gid;
            $db->insert_query('settings', $setting);
        }
    
        rebuild_settings();

    // TEMPLATES ERSTELLEN// Template Gruppe für jedes Design erstellen
    $templategroup = array(
      "prefix" => "relations",
      "title" => $db->escape_string("Beziehungssystem"),
  );

  $db->insert_query("templategroups", $templategroup);

    // Beziehungsanzeige im Profil
    $insert_array = array(
        'title'		=> 'relations',
        'template'	=> $db->escape_string('<table border="0" cellspacing="0" cellpadding="5" class="tborder">
        <tr>
            <td class="thead">
                <strong>{$lang->relations}</strong>
            </td>
        </tr>
        <tr>
            <td class="trow1" align="center">
                {$relations_type}
            </td>
        </tr>
    </table>
    <br />'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);
    
    // Beziehung hinzufügen
    $insert_array = array(
        'title'		=> 'relations_add',
        'template'	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder tfixed">
        <tr>
        <td class="thead"><strong>{$lang->relations_add}</strong></td>
        </tr>
        <tr>
        <td class="trow1" align="center">
            <form id="add_relation" method="post" action="member.php?action=profile&uid={$memprofile[\'uid\']}">
                            <table style="width: 100%; margin: auto; margin-top: 10px;">                         
                                <tbody>
                                    <tr>
                                        <td>
                                            <input type="text" class="textbox" name="relationship" id="relationship" placeholder="{$lang->relations_add_relationship}" style="width: 98%;height: 17px;margin-bottom: 0;" required>   
                                        </td>                       
                                        <td>
                                            <select name=\'type\' id=\'type\' style="width: 100%;" required>       
                                                <option value="">Kategorie wählen</option>
                                                {$cat_select}	
                                            </select>		
                                        </td>                                                    
                                    </tr>
                                    <tr>
                                        <td colspan="2"><textarea name="description" id="description" class="textfield" placeholder="{$lang->relations_add_desc}" style="width: 100%; height: 100px"  required></textarea></td>
                                    </tr>			
                                </tbody>
                            </table>
                            <br>
                            <div style="width: 145px; margin: auto;"> 
                                <input type="submit" name="add_relation" id="submit" class="button" value="{$lang->relations_add_send}">
                            </div>                                  
                        </form>
            </td>
        </tr>
        </table>
        <br />'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Beziehung hinzufügen - OHNE Text
    $insert_array = array(
        'title'		=> 'relations_add_notext',
        'template'	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder tfixed">
        <tr>
        <td class="thead"><strong>{$lang->relations_add}</strong></td>
        </tr>
        <tr>
        <td class="trow1" align="center">
            <form id="add_relation" method="post" action="member.php?action=profile&uid={$memprofile[\'uid\']}">
                            <table style="width: 100%; margin: auto; margin-top: 10px;">                         
                                <tbody>
                                    <tr>
                                        <td>
                                            <input type="text" class="textbox" name="relationship" id="relationship" placeholder="{$lang->relations_add_relationship}" style="width: 98%;height: 17px;margin-bottom: 0;" required>   
                                        </td>                       
                                        <td>
                                            <select name=\'type\' id=\'type\' style="width: 100%;" required>       
                                                <option value="">Kategorie wählen</option>
                                                {$cat_select}	
                                            </select>		
                                        </td>                                                    
                                    </tr>
                                </tbody>
                            </table>
                            <br>
                            <div style="width: 145px; margin: auto;"> 
                                <input type="submit" name="add_relation" id="submit" class="button" value="{$lang->relations_add_send}">
                            </div>                                  
                        </form>
            </td>
        </tr>
        </table>
        <br />'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // NPC hinzufügen
    $insert_array = array(
        'title'		=> 'relations_add_npc',
        'template'	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder tfixed">
        <tr>
        <td class="thead"><strong>{$lang->relations_add_npc}</strong></td>
        </tr>
        <tr>
        <td class="trow1" align="center">
             <form id="add_npc" method="post" action="member.php?action=profile&uid={$memprofile[\'uid\']}">
                    <table cellpadding="0" cellspacing="4" border="0" width="32%">	
                        <tbody>
                            <tr>
                                <td align="right">
                                    <input type="text" class="textbox" name="npc_name" id="npc_name" placeholder="{$lang->relations_add_npc_name}" style="width: 335px;margin-bottom:5px;" required>
                                    <input type="text" class="textbox" name="npc_info" id="npc_info" placeholder="{$lang->relations_add_npc_info}" style="width:335px;margin-bottom:5px;" required>
                                    <input type="text" class="textbox" name="relationship" id="relationship" placeholder="{$lang->relations_add_relationship}" style="width: 335px;margin-bottom: 5px;padding: 3px;" required>
                                    <select name=\'type\' id=\'type\' style="width: 100%;" required>       		
                                        <option value="">Kategorie wählen</option>		
                                        {$cat_select}			
                                    </select>      
                                </td>
                                <td>
                                    <textarea name="description" id="description" class="textfield" style="min-width:350px; min-height: 103px;"  placeholder="{$lang->relations_add_desc}"  required></textarea>
                                </td>    
                            </tr>
                                                 
                            <tr>
                                <td valign="bottom" align="center" colspan="2">
                                    <input type="text" class="textbox" name="npc_search" id="npc_search" placeholder="{$lang->relations_add_npc_search}" style="width: 99%;margin-bottom: 0;padding: 3px;">
                                </td>    
                            </tr>
                                                    
                            <tr>
                                <td valign="bottom" align="center" colspan="2">
                                    <input type="submit" name="add_npc" id="submit" class="button" value="{$lang->relations_add_npc_send}">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
        </td>
        </tr>
        </table>
        <br />'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // NPC hinzufügen - OHNE TEXT
    $insert_array = array(
        'title'		=> 'relations_add_npc_notext',
        'template'	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder tfixed">
        <tr>
        <td class="thead"><strong>{$lang->relations_add_npc}</strong></td>
        </tr>
        <tr>
        <td class="trow1" align="center">
            <form id="add_npc" method="post" action="member.php?action=profile&uid={$memprofile[\'uid\']}">
            <input type="text" class="textbox" name="npc_name" id="npc_name" placeholder="{$lang->relations_add_npc_name}" style="width:49%" required/>
            <input type="text" class="textbox" name="npc_info" id="npc_info" placeholder="{$lang->relations_add_npc_info}" style="width:49%" required/>
                <br><br>
            <input type="text" class="textbox" name="relationship" id="relationship" placeholder="{$lang->relations_add_relationship}" style="width:49%" required/>    
            <input type="text" class="textbox" name="npc_search" id="npc_search" placeholder="{$lang->relations_add_npc_search}" style="width:49%">
                <br><br>
        <select name=\'type\' id=\'type\' style="width:100%" required>       		
                                        <option value="">Kategorie wählen</option>		
                                        {$cat_select}			
                                    </select>  
                <br><br>
                <input type="submit" name="add_npc" id="submit" class="button" value="{$lang->relations_add_npc_send}">
                </form>
        </td>
        </tr>
        </table>
        <br />'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Einzelne Beziehung
    $insert_array = array(
        'title'		=> 'relations_bit',
        'template'	=> $db->escape_string('<table cellspacing=2 cellpadding=2>
        <tr> 
            <td colspan=2 class=tcat>{$username}</td>
        </tr>
        <tr>
            <td colspan=2 align=center>{$relationship}</td>
        </tr>
        <tr>
            <td colspan=2 align=center>{$relation[\'fidXX\']} | {$relation[\'fidXX\']} | Fakt</td>
        </tr>
        <tr> 
            <td width=17%>{$useravatar}</td>    
            <td>{$description}</td>     
        </tr>
        <tr>
            <td colspan=2 align=center>{$option}</td>
        </tr>
    </table>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Einzelne Beziehung - OHNE TEXT
    $insert_array = array(
        'title'		=> 'relations_bit_notext',
        'template'	=> $db->escape_string('<table cellspacing=2 cellpadding=2>
        <tr> 
            <td colspan=2 class=tcat>{$username}</td>
        </tr>
        <tr>
            <td colspan=2 align=center></td>
        </tr>
        <tr> 
            <td width=17%>{$useravatar}</td>    
            <td>{$relationship}<br>
            {$relation[\'fidXX\']} | {$relation[\'fidXX\']} | Fakt</td>     
        </tr>
        <tr>
            <td colspan=2 align=center>{$option}</td>
        </tr>
    </table>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Einzelne NPC Beziehung
    $insert_array = array(
        'title'		=> 'relations_bit_npc',
        'template'	=> $db->escape_string('<table cellspacing=2 cellpadding=2>
        <tr> 
            <td colspan=2 class=tcat>[NPC] {$npc_name} <span style="float:right;padding:2px">{$npc_search}</span></td>
        </tr>
        <tr>
            <td colspan=2 align=center>{$relationship}</td>
        </tr>
        <tr>
            <td colspan=2 align=center>{$npc_info}</td>
        </tr>
        <tr> 
            <td width=17%>{$npc_avatar}</td>    
            <td>{$description}</td>     
        </tr>
        <tr>
            <td colspan=2 align=center>{$option}</td>
        </tr>
    </table>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Einzelne NPC Beziehung - OHNE TEXT
    $insert_array = array(
        'title'		=> 'relations_bit_npc_notext',
        'template'	=> $db->escape_string('<table cellspacing=2 cellpadding=2>
        <tr> 
            <td colspan=2 class=tcat>[NPC] {$npc_name} <span style="float:right;padding:2px">{$npc_search}</span></td>
        </tr>
        <tr> 
            <td width=17%>{$npc_avatar}</td>    
            <td>
                {$relationship}
                <br>
                {$npc_info}
            </td>     
        </tr>
        <tr>
            <td colspan=2 align=center>{$option}</td>
        </tr>
    </table>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Bearbeitung im Profil
    $insert_array = array(
        'title'		=> 'relations_edit',
        'template'	=> $db->escape_string('<form action="member.php?action=profile&uid={$memprofile[\'uid\']}" method="post">
        <input type="hidden" name="rid" id="rid" value="{$rid}" />	
        <table style="margin: 10px;">                         	
            <tbody>
                <tr>
                    <td>
                        <input type="text" class="textbox" name="relationship" id="relationship" value="{$relationship}" required> 
                    </td>                       
                    <td>
                        <select name=\'type\' id=\'type\' required>       
                            <option value="{$type}">{$type}</option>
                            {$cat_select}	
                        </select>		
                    </td>                                                    
                </tr>
                <tr>
                    <td colspan="2">
                        <textarea name="description" id="description" class="textfield" style="width: 100%;height:120px" required>{$description}</textarea>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" align="center">
                        <input type="submit" name="edit_relation" id="submit" class="button" value="{$lang->relations_edit_send}">
                    </td>
                </tr>
            </tbody>
        </table>
    </form>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Bearbeitung im Profil - OHNE TEXT
    $insert_array = array(
        'title'		=> 'relations_edit_notext',
        'template'	=> $db->escape_string('<form action="member.php?action=profile&uid={$memprofile[\'uid\']}" method="post">
        <input type="hidden" name="rid" id="rid" value="{$rid}" />	
        <table style="margin: 10px;">                         	
            <tbody>
                <tr>
                    <td>
                        <input type="text" class="textbox" name="relationship" id="relationship" value="{$relationship}" required> 
                    </td>                       
                    <td>
                        <select name=\'type\' id=\'type\' required>       
                            <option value="{$type}">{$type}</option>
                            {$cat_select}	
                        </select>		
                    </td>                                                    
                </tr>
                <tr>
                    <td colspan="2" align="center">
                        <input type="submit" name="edit_relation" id="submit" class="button" value="{$lang->relations_edit_send}">
                    </td>
                </tr>
            </tbody>
        </table>
    </form>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Bearbeitung NPC
    $insert_array = array(
        'title'		=> 'relations_edit_npc',
        'template'	=> $db->escape_string('<form action="member.php?action=profile&uid={$memprofile[\'uid\']}" method="post">
        <input type="hidden" name="rid" id="rid" value="{$rid}" />
        <table style="margin: 10px;">                         	
            <tbody>
                <tr>
                    <td>
                        <input type="text" class="textbox" name="npc_name" id="npc_name" value="{$relation[\'npc_name\']}" required> 
                    </td>
                    <td>
                         <input type="text" class="textbox" name="npc_info" id="npc_info" value="{$npc_info}" required> 
                    </td>
                </tr>
                <tr>
                    <td>
                         <input type="text" class="textbox" name="relationship" id="relationship" value="{$relationship}" required> 
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
                        <textarea name="description" id="description" class="textfield" style="width: 98%;height:120px" required>{$description}</textarea>
                    </td>
                </tr>
                <tr>
                <td align="center" colspan="2">
                    <input type="text" class="textbox" name="npc_search" id="npc_search"  style="width: 98%" placeholder="{$lang->relations_add_npc_search}" value="{$relation[\'npc_search\']}">
                </td>    
            </tr>
                <tr>
                    <td colspan="2" align="center">
                        <input type="submit" name="edit_npc" id="submit" class="button" value="{$lang->relations_edit_npc_send}">
                    </td>
                </tr>
            </tbody>
        </table>
    </form>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // Bearbeitung NPC - OHNE TEXT
    $insert_array = array(
        'title'		=> 'relations_edit_npc_notext',
        'template'	=> $db->escape_string('<form action="member.php?action=profile&uid={$memprofile[\'uid\']}" method="post">
        <input type="hidden" name="rid" id="rid" value="{$rid}" />
        <table style="margin: 10px;">                         	
            <tbody>
                <tr>
                    <td>
                        <input type="text" class="textbox" name="npc_name" id="npc_name" value="{$relation[\'npc_name\']}" required> 
                    </td>
                    <td>
                         <input type="text" class="textbox" name="npc_info" id="npc_info" value="{$npc_info}" required> 
                    </td>
                </tr>
                <tr>
                    <td>
                         <input type="text" class="textbox" name="relationship" id="relationship" value="{$relationship}" required> 
                    </td>
                    <td>
                        <select name=\'type\' id=\'type\' style="width: 100%;" required>       
                            <option value="{$type}">{$type}</option>
                            {$cat_select}	
                        </select>		
                    </td>                                                    
                </tr>
                <tr>
                <td align="center" colspan="2">
                    <input type="text" class="textbox" name="npc_search" id="npc_search"  style="width: 98%" placeholder="Gibt es ein Gesuch zu diesem NPC? Hier den Link angeben" value="{$relation[\'npc_search\']}">
                </td>    
            </tr>
                <tr>
                    <td colspan="2" align="center">
                        <input type="submit" name="edit_npc" id="submit" class="button" value="NPC editieren">
                    </td>
                </tr>
            </tbody>
        </table>
    </form>'),
        'sid'		=> '-2',
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
                    <div style="text-align:center;margin:10px auto;">{$lang->relations_none}</div>
                </td>
            </tr>
        </table>
    </div>'),
        'sid'		=> '-2',
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
        'sid'		=> '-2',
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
    find_replace_templatesets('member_profile', '#'.preg_quote('{$awaybit}').'#', '{$awaybit} {$relations_show} {$relations_add}');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function relations_deactivate()
{
    global $db, $cache;

    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
    find_replace_templatesets("member_profile", "#".preg_quote('{$relations_show} {$relations_add}')."#i", '', 0);

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
    global $db, $mybb, $memprofile, $templates, $theme, $cat_select, $relations_add, $relations_show, $relations_type, $relations_bit, $option, $lang;

    $lang->load('relations');

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
    $relations_npc_setting = $mybb->settings['relations_npc'];
    $relations_description_setting = $mybb->settings['relations_description'];

    // AUSWAHLMÖGLICHKEIT DROPBOX GENERIEREN
    // Kategorien
    $relations_cat = explode (", ", $relations_type_setting);
    foreach ($relations_cat as $cat) {
        $cat_select .= "<option value='{$cat}'>{$cat}</option>";
    }

    // GÄSTE DÜRFEN GAR NICHT HINZUFÜGEN
    if($mybb->user['uid'] != '0'){
        // Man kann sich nicht selbst hinzufügen
        if($memprofile['uid'] != $mybb->user['uid']){
            // TEXT EINSTELLUNGEN
            if ($relations_description_setting == 1){
                // Mit Text
                eval("\$relations_add = \"" . $templates->get ("relations_add") . "\";");
            } else {
                // Ohne Text
                eval("\$relations_add = \"" . $templates->get ("relations_add_notext") . "\";");
            }
        } 
        // Wenn NPCs erlaubt sind, dann stattdessen das NPC Formular anzeigen
        elseif ($relations_npc_setting == '1') {
            // TEXT EINSTELLUNGEN
            if ($relations_description_setting == 1){
                // Mit Text
                eval("\$relations_add = \"" . $templates->get ("relations_add_npc") . "\";");
            } else {
                // Ohne Text
                eval("\$relations_add = \"" . $templates->get ("relations_add_npc_notext") . "\";");
            }
        }
        else {
            $relations_add = "";
        }
    }

    // EINTRAGEN VON VORHANDENEN ACCOUNTS   
    if(isset($_POST['add_relation'])) {
    
        $new_relation = array(
            "relation_by" => $relation_user,
            "relation_with" => $relation_profile,
            "type" => $db->escape_string($mybb->get_input('type')),
            "relationship" => $db->escape_string($mybb->get_input('relationship')),
            "description" => $db->escape_string($mybb->get_input('description')),
            "npc_name" => $db->escape_string($mybb->get_input('npc_name')),
            "npc_info" => $db->escape_string($mybb->get_input('npc_info')),
            "npc_search" => $db->escape_string($mybb->get_input('npc_search')),
        );

        $db->insert_query("relations", $new_relation);

        // MyALERTS STUFF
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relations_new');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$memprofile['uid'], $alertType, (int)$relation_user);
                $alert->setExtraDetails([
                    'username' => $mybb->user['username'],
                    'from' => $mybb->user['uid'],
                    'relationship' => $mybb->get_input('relationship'),
                    'type' => $mybb->get_input('type')
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }

        redirect("member.php?action=profile&uid={$relation_user}", "{$lang->relations_redirect_add}");
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
        "npc_info" => $db->escape_string($mybb->get_input('npc_info')),
        "npc_search" => $db->escape_string($mybb->get_input('npc_search')),
        );

        $db->insert_query("relations", $new_npc);
        redirect("member.php?action=profile&id={$relation_user}", "{$lang->relations_redirect_add_npc}");
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
            $npc_info = "";
            $npc_search = "";
            $npc_name = "";

            // MIT INFOS FÜLLEN
            $rid = $relation['rid'];
            $relation_by = $relation['relation_by'];
            $relation_with = $relation['relation_with'];
            $type = $relation['type'];
            $relationship = $relation['relationship'];
            $description = $parser->parse_message($relation['description'], $options);
            $npc_info = $relation['npc_info'];
            $npc_name = $relation['npc_name'];

            // NPCs
            if ($relation_with == 0){

                // Avatar bilden
                $npc_avatar = "<img src='{$theme['imgdir']}/{$relations_avatar_setting}' width='100%'>";

                // Link zum Gesuch bilden	
			    if(!empty($relation['npc_search'])){
                    $npc_search = "<a href=\"{$relation['npc_search']}\" original-title=\"Zum Gesuch\"><i class=\"fas fa-search\"></i></a>";
                } else {
                    $npc_search = "";
                }

                // LÖSCHEN UND BEARBEITEN VON NPCS
				if($relation_user == $relation_by){
                    // TEXT EINSTELLUNGEN
                 if ($relations_description_setting == 1){
                    // Mit Text
                    eval("\$edit_npc = \"" . $templates->get("relations_edit_npc") . "\";");
                } else {
                    // Ohne Text
                    eval("\$edit_npc = \"" . $templates->get("relations_edit_npc_notext") . "\";");
                }
                    $edit = "<a onclick=\"$('#edit_{$relation['rid']}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">Bearbeiten</a>";
                    $option = "<a href=\"member.php?action=profile&delrel={$rid}\">Löschen</a> # {$edit}<div class=\"modal\" id=\"edit_{$relation['rid']}\" style=\"display: none;width:auto;\">{$edit_npc}</div>";
                 }
                 else {
                     $option = "";
                 }
            
                 // TEXT EINSTELLUNGEN
                 if ($relations_description_setting == 1){
                    // Mit Text
                   eval("\$relations_bit .= \"" . $templates->get ("relations_bit_npc") . "\";");
                } else {
                    // Ohne Text
                   eval("\$relations_bit .= \"" . $templates->get ("relations_bit_npc_notext") . "\";");
                }
            } 

            // NORMALE USER
            else {

                // FARBIGE USERNAME 
                $profilelink = format_name($relation['username'], $relation['usergroup'], $relation['displaygroup']);
                $username = build_profile_link($profilelink, $relation['relation_with']);

                // AVATARE
                // Einstellung für Gäste Avatare ausblenden
                if ($relations_avatar_guest_setting == 1){
                    // Gäste und kein Avatar - Standard-Avatar
                    if ($mybb->user['uid'] == '0' || $relation['avatar'] == '') {
                        $useravatar  = "<img src='{$theme['imgdir']}/{$relations_avatar_setting}' width='100%'>";
                    } else {
                        $useravatar  = "<img src='{$relation['avatar']}' width='100%'>";
                    }

                } else {
                    // kein Avatar - Standard-Avatar
                    if ($relation['avatar'] == '') {
                        $useravatar  = "<img src='{$theme['imgdir']}/{$relations_avatar_setting}' width='100%'>";
                    } else {
                        $useravatar  = "<img src='{$relation['avatar']}' width='100%'>";
                    }
                }

                

                // BEARBEITEN - sieht nur der ersteller
                if($relation_user == $relation_by){
                       // TEXT EINSTELLUNGEN
                 if ($relations_description_setting == 1){
                    // Mit Text
                 eval("\$edit_user = \"" . $templates->get("relations_edit") . "\";");
                } else {
                    // Ohne Text
                 eval("\$edit_user = \"" . $templates->get("relations_edit_notext") . "\";");
                }
                $edit_button = "<a onclick=\"$('#edit_{$relation['rid']}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">Bearbeiten</a>";
                $edit = "# {$edit_button}<div class=\"modal\" id=\"edit_{$relation['rid']}\" style=\"display: none;width:auto;\">{$edit_user}</div>";
                } else {
                    $edit = "";
                }
               
                // OPTIONEN - BUTTON 
				if($relation_user == $relation_by OR $relation_user == $relation_with){
                    $option = "<a href=\"member.php?action=profile&delrel={$rid}\">Löschen</a> {$edit}";
                 }
                 else {
                     $option = "";
                 }

                 // TEXT EINSTELLUNGEN
                 if ($relations_description_setting == 1){
                     // Mit Text
                    eval("\$relations_bit .= \"" . $templates->get ("relations_bit") . "\";");
                 } else {
                     // Ohne Text
                    eval("\$relations_bit .= \"" . $templates->get ("relations_bit_notext") . "\";");
                 }
            }
            
        }

        // Die verschiedenen Kategorien auslesen lassen
        eval("\$relations_type .= \"" . $templates->get ("relations_type") . "\";");
    }

    // RELATIONS BEARBEITEN
	if (isset($mybb->input['edit_relation'])) {
		$rid = $mybb->input['rid'];

		$relation_edit = array(
            "type" => $db->escape_string($mybb->get_input('type')),
            "relationship" => $db->escape_string($mybb->get_input('relationship')),
            "description" => $db->escape_string($mybb->get_input('description')),
		);
        
		$db->update_query("relations", $relation_edit, "rid='{$rid}'");

        // MyALERTS STUFF
    $query_alert_edit = $db->simple_select("relations", "*", "rid = '{$rid}'");
    while ($alert_edit = $db->fetch_array ($query_alert_edit)) {
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $user = get_user($alert['relation_with']);
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relations_alert_edit');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$alert_edit['relation_with'], $alertType, (int)$rid);
                $alert->setExtraDetails([
                    'username' => $mybb->user['username'],
                    'from' => $mybb->user['uid'],
                    'relationship' => $mybb->get_input('relationship'),
                    'type' => $mybb->get_input('type')
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }
    }
		redirect("member.php?action=profile&uid={$relation_user}", "{$lang->relations_redirect_edit}");
	}

    // NPCs BEARBEITEN
	if (isset($mybb->input['edit_npc'])) {
		$rid = $mybb->input['rid'];

		$npc_edit = array(
            "type" => $db->escape_string($mybb->get_input('type')),
            "relationship" => $db->escape_string($mybb->get_input('relationship')),
            "description" => $db->escape_string($mybb->get_input('description')),
            "npc_name" => $db->escape_string($mybb->get_input('npc_name')),
            "npc_info" => $db->escape_string($mybb->get_input('npc_info')),
            "npc_search" => $db->escape_string($mybb->get_input('npc_search')),
		);

		$db->update_query("relations", $npc_edit, "rid='{$rid}'");
		redirect("member.php?action=profile&uid={$relation_user}", "{$lang->relations_redirect_edit_npc}");
	}

    // Relations löschen
	if(isset($mybb->input['delrel'])) {

    // MyALERTS STUFF
    $query_alert = $db->simple_select("relations", "*", "rid = '{$mybb->input['delrel']}'");
    while ($alert_del = $db->fetch_array ($query_alert)) {
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $user = get_user($alert['relation_with']);
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('relations_delete');
             // WENN DER EINTRÄGER GELÖSCHT HAT
             if ($alertType != NULL && $alertType->getEnabled() && $mybb->user['uid'] != $alert_del['relation_with']) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$alert_del['relation_with'], $alertType, (int)$mybb->input['delrel']);
                $alert->setExtraDetails([
                    'username' => $mybb->user['username'],
                    'from' => $mybb->user['uid'],
                    'relationship' => $alert_del['relationship'],
                    'type' => $alert_del['type']
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            } // WENN DER EINGETRAGENE LÖSCHT
            elseif ($alertType != NULL && $alertType->getEnabled() && $mybb->user['uid'] == $alert_del['relation_with']) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$alert_del['relation_by'], $alertType, (int)$mybb->input['delrel']);
                $alert->setExtraDetails([
                    'username' => $mybb->user['username'],
                    'from' => $mybb->user['uid'],
                    'relationship' => $alert_del['relationship'],
                    'type' => $alert_del['type']
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }
    }

		$db->delete_query("relations", "rid = '".$mybb->input['delrel']."'");
		redirect("member.php?action=profile&uid={$relation_user}", "{$lang->relations_redirect_delete}");
	}


    // Im Profil anzeigen lassen
    eval("\$relations_show .= \"" . $templates->get ("relations") . "\";");

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
                $alertContent['username'],
                $alertContent['from'],
                $alertContent['relationship'],
                $alertContent['type']         
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
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/member.php?action=profile&uid='.$alertContent['from'];
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
                $alertContent['username'],
                $alertContent['from'],
                $alertContent['relationship'],
                $alertContent['type']         
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
	        $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/member.php?action=profile&uid='.$alertContent['from'];
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
                $alertContent['username'],
                $alertContent['from'],
                $alertContent['relationship'],
                $alertContent['type'] 
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
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/member.php?action=profile&uid='.$alertContent['from'];
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
