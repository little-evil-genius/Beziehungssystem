# Beziehungssystem
Das Plugin erweitert das Board um ein Beziehungssystem. User können andere Accounts zu ihrer Beziehungskiste hinzufügen. 
Je nach Einstellungen können User auch noch NPCs hinzufügen. Auch kann ein Team im ACP festlegen, ob die User einen Beschreibungstext hinzufügen können.
Die Kategorien für die Beziehungskiste können im ACP festgelegt und ob Gäste die Avatare sehen können oder nicht. 
Die Bezeichnung für den Default Avatar (wird angezeigt, wenn kein Avatar vorhanden ist oder ein NPC eingetragen wurde oder Gästen angezeigt werden sollen) kann auch manuell festgelegt werden.

#Datenbank-Änderungen
Hinzugefügte Tabellen:
- PRÄFIX_relations

#Neue Template-Gruppe innerhalb der Design-Templates
- Beziehungssystem

#Neue Templates (nicht global!)
- relations
- relations_add
- relations_add_notext
- relations_add_npc
- relations_add_npc_notext
- relations_bit
- relations_bit_notext
- relations_bit_npc
- relations_bit_npc_notext
- relations_edit
- relations_edit_notext
- relations_edit_npc
- 
- relations_none
- relations_type

#Template Änderungen - neue Variablen
- member_profile - {$relations_show} {$relations_add} 

#ACP-Einstellungen - Beziehungssystem
- Beziehungskategorien
- Standard-Avatar
- Avatar ausblenden
- Relationtexte
- NPCs

#Demo 
Das Layout ist extrems simple und einfach gehalten. In dem Bit Tpls kann man komplett mit div arbeiten.

#Support
Wie viele von euch wissen, bin ich noch kein wirklicher Profi und habe auch noch nicht allzu viele Plugins geschrieben, somit ist teilweise mein Wissen auch begrenzt und ich weiß nicht immer sofort, was die Lösung ist. 
Aber ich versuche mein bestes, auch wenn es manchmal etwas langsamer vorangeht. Ich hab auch kein Problem, wenn jemand anderes Support gibt und somit hilft.
