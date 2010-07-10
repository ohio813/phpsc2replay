<?php
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
define("SC2_TYPEUNIT", 1); // normal units
define("SC2_TYPEWORKER", 2); // workers, Drone/SCV/Probe, MULE calldown
define("SC2_TYPEBUILDING", 3); // any kind of buildings
define("SC2_TYPEADDON", 4); // building addons(terran)
define("SC2_TYPEUPGRADE", 5); // any kind of upgrade
define("SC2_TYPEABILITY", 6); // a unit or building ability that is not an upgrade
define("SC2_TYPEBUILDINGUPGRADE",7); // transform a building to another building
define("SC2_TYPEGEN", 8); // anything that doesn't fit into earlier categories

define("SC2_SUBTYPECREATE", 1); // opposite of cancel
define("SC2_SUBTYPECANCEL", 2); // cancel

$sc2_abilityCodes = array(
// terran
0x030D00 => array('desc' => 'builds Point defense drone (Raven)', 'name' => 'Point defense drone (Raven)', 'type' => SC2_TYPEABILITY),
0x040900 => array('desc' => 'uses Seeker missile (Raven)', 'name' => 'Seeker missile (Raven)', 'type' => SC2_TYPEABILITY),
0x040A00 => array('desc' => 'calls down a MULE', 'name' => 'MULE', 'type' => SC2_TYPEWORKER, 'subtype' => SC2_SUBTYPECREATE, 'min' => 0, 'gas' => 0, 'sup' => 0),
0x050600 => array('desc' => 'sets rally point (Generic)', 'name' => 'rally point (Generic)', 'type' => SC2_TYPEGEN),
0x060000 => array('desc' => 'uses Stim pack (Marauder)', 'name' => 'Stim pack', 'type' => SC2_TYPEABILITY),
0x060100 => array('desc' => 'calls down supplies (Orbital command)', 'name' => 'Calldown: Extra supplies (Orbital command)', 'type' => SC2_TYPEABILITY),
0x060200 => array('desc' => 'uses 250mm strike cannons (Thor)', 'name' => '250mm strike cannons (Thor)', 'type' => SC2_TYPEABILITY),
0x060701 => array('desc' => 'sets rally point (Orbital command)', 'name' => 'rally point (Orbital command)', 'type' => SC2_TYPEGEN),
0x060B00 => array('desc' => 'cancels a build in progress (Starport)', 'name' => 'cancel (Starport)', 'type' => SC2_TYPEGEN, 'subtype' => SC2_SUBTYPECANCEL), // medivac
0x060B01 => array('desc' => 'cancels a build in progress', 'name' => 'cancel', 'type' => SC2_TYPEGEN, 'subtype' => SC2_SUBTYPECANCEL),
0x060F00 => array('desc' => 'repairs', 'name' => 'repair', 'type' => SC2_TYPEGEN, 'subtype' => SC2_SUBTYPECANCEL),
0x070000 => array('desc' => 'builds a Command center', 'name' => 'Command center', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 400, 'gas' => 0),
0x070001 => array('desc' => 'builds a Supply depot', 'name' => 'Supply depot', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 0),
0x070002 => array('desc' => 'builds a Refinery', 'name' => 'Refinery', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 75, 'gas' => 0),
0x070003 => array('desc' => 'builds a Barracks', 'name' => 'Barracks', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 0),
0x070004 => array('desc' => 'builds an Engineering Bay', 'name' => 'Engineering Bay', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 125, 'gas' => 0),
0x070005 => array('desc' => 'builds a Missile turret', 'name' => 'Missile turret', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 0),
0x070006 => array('desc' => 'builds a Bunker', 'name' => 'Bunker', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 0),
0x070008 => array('desc' => 'builds a Sensor tower', 'name' => 'Sensor tower', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 125, 'gas' => 100),
0x070009 => array('desc' => 'builds a Ghost academy', 'name' => 'Ghost academy', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 50),
0x07000A => array('desc' => 'builds a Factory', 'name' => 'Factory', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 100),
0x07000B => array('desc' => 'builds a Starport', 'name' => 'Starport', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 100),
0x07000D => array('desc' => 'builds an Armory', 'name' => 'Armory', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 100),
0x07000F => array('desc' => 'builds a Fusion core', 'name' => 'Fusion core', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x070200 => array('desc' => 'uses Stim pack (mixed units)', 'name' => 'Stim pack', 'type' => SC2_TYPEABILITY),
0x070300 => array('desc' => 'uses Cloak (Ghost)', 'name' => 'Cloak (Ghost)', 'type' => SC2_TYPEABILITY),
0x070301 => array('desc' => 'uses Decloak (Ghost)', 'name' => 'Decloak (Ghost)', 'type' => SC2_TYPEABILITY),
0x070400 => array('desc' => 'uses Sniper round (Ghost)', 'name' => 'Sniper round (Ghost)', 'type' => SC2_TYPEABILITY),
0x070600 => array('desc' => 'uses Siege mode (Siege tank)', 'name' => 'Siege mode (Siege tank)', 'type' => SC2_TYPEABILITY),
0x070700 => array('desc' => 'uses Tank mode (Siege tank)', 'name' => 'Tank mode (Siege tank)', 'type' => SC2_TYPEABILITY),
0x070A00 => array('desc' => 'uses Scanner sweep (Orbital command)', 'name' => 'Scanner sweep (Orbital command)', 'type' => SC2_TYPEABILITY),
0x070B00 => array('desc' => 'uses Yamato cannon (Battlecruiser)', 'name' => 'Yamato cannon (Battlecruiser)', 'type' => SC2_TYPEABILITY),
0x070C00 => array('desc' => 'uses Assault mode (Viking)', 'name' => 'Assault mode (Viking)', 'type' => SC2_TYPEABILITY),
0x070D00 => array('desc' => 'uses Fighter mode (Viking)', 'name' => 'Fighter mode (Viking)', 'type' => SC2_TYPEABILITY),
0x070E03 => array('desc' => 'unloads unit (Bunker)', 'name' => 'unload (Bunker)', 'type' => SC2_TYPEABILITY),
0x080000 => array('desc' => 'lifts off (Command center)', 'name' => 'liftoff (Command center)', 'type' => SC2_TYPEABILITY),
0x080100 => array('desc' => 'lands (Command center)', 'name' => 'land (Command center)', 'type' => SC2_TYPEABILITY),
0x080200 => array('desc' => 'builds a Tech lab(Barracks)', 'name' => 'Tech lab(Barracks)', 'type' => SC2_TYPEADDON, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 25),
0x080201 => array('desc' => 'builds a Reactor(Barracks)', 'name' => 'Reactor(Barracks)', 'type' => SC2_TYPEADDON, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 50),
0x080300 => array('desc' => 'lifts off (Barracks)', 'name' => 'liftoff (Barracks)', 'type' => SC2_TYPEABILITY),
0x080400 => array('desc' => 'builds a Tech lab(Factory)', 'name' => 'Tech lab(Factory)', 'type' => SC2_TYPEADDON, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 25),
0x080401 => array('desc' => 'builds a Reactor(Factory)', 'name' => 'Reactor(Factory)', 'type' => SC2_TYPEADDON, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 50),
0x080500 => array('desc' => 'lifts off (Factory)', 'name' => 'liftoff (Factory)', 'type' => SC2_TYPEABILITY),
0x080600 => array('desc' => 'builds a Tech lab(Starport)', 'name' => 'Tech lab(Starport)', 'type' => SC2_TYPEADDON, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 25),
0x080601 => array('desc' => 'builds a Reactor(Starport)', 'name' => 'Reactor(Starport)', 'type' => SC2_TYPEADDON, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 50),
0x080700 => array('desc' => 'lifts off (Starport)', 'name' => 'liftoff (Starport)', 'type' => SC2_TYPEABILITY),
0x080800 => array('desc' => 'lands (Factory)', 'name' => 'land (Factory)', 'type' => SC2_TYPEABILITY),
0x080900 => array('desc' => 'lands (Starport)', 'name' => 'land (Starport)', 'type' => SC2_TYPEABILITY),
0x080A00 => array('desc' => 'trains an SCV', 'name' => 'SCV', 'type' => SC2_TYPEWORKER, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 0, 'sup' => 1),
0x080B00 => array('desc' => 'lands (Barracks)', 'name' => 'land (Barracks)', 'type' => SC2_TYPEABILITY),
0x080C00 => array('desc' => 'lowers Supply depot', 'name' => 'lower Supply depot', 'type' => SC2_TYPEABILITY),
0x080E00 => array('desc' => 'trains a Marine', 'name' => 'Marine', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 0, 'sup' => 1),
0x080E01 => array('desc' => 'trains a Reaper', 'name' => 'Reaper', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 25, 'sup' => 1),
0x080E02 => array('desc' => 'trains a Ghost', 'name' => 'Ghost', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150, 'sup' => 2),
0x080E03 => array('desc' => 'trains a Marauder', 'name' => 'Marauder', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 75, 'gas' => 25, 'sup' => 2),
0x080F01 => array('desc' => 'builds a Siege tank', 'name' => 'Siege tank', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 125, 'sup' => 3),
0x080F04 => array('desc' => 'builds a Thor', 'name' => 'Thor', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 300, 'gas' => 200, 'sup' => 6),
0x080F05 => array('desc' => 'builds a Hellion', 'name' => 'Hellion', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 0, 'sup' => 2),
0x090000 => array('desc' => 'builds a Medivac', 'name' => 'Medivac', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100, 'sup' => 2),
0x090001 => array('desc' => 'builds a Banshee', 'name' => 'Raven', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 100, 'sup' => 3),
0x090002 => array('desc' => 'builds a Raven', 'name' => 'Raven', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 200, 'sup' => 2),
0x090003 => array('desc' => 'builds a Battlecruiser', 'name' => 'Battlecruiser', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 400, 'gas' => 300, 'sup' => 6),
0x090004 => array('desc' => 'builds a Viking', 'name' => 'Viking', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 75, 'sup' => 2),
0x090100 => array('desc' => 'researches Hi-sec auto tracking', 'name' => 'Hi-sec auto tracking', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090101 => array('desc' => 'researches Structure armor', 'name' => 'Structure armor', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x090102 => array('desc' => 'researches Infantry weapons level 1', 'name' => 'Infantry weapons L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090103 => array('desc' => 'researches Infantry weapons level 2', 'name' => 'Infantry weapons L2', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 175, 'gas' => 175),
0x090104 => array('desc' => 'researches Infantry weapons level 3', 'name' => 'Infantry weapons L3', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 250, 'gas' => 250),
0x090105 => array('desc' => 'researches Neosteel frame', 'name' => 'Neosteel frame', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090106 => array('desc' => 'researches Infantry armor level 1', 'name' => 'Infantry armor L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090107 => array('desc' => 'researches Infantry armor level 2', 'name' => 'Infantry armor L2', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 175, 'gas' => 175),
0x090108 => array('desc' => 'researches Infantry armor level 3', 'name' => 'Infantry armor L3', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 250, 'gas' => 250),
0x090203 => array('desc' => 'researches Nitro packs', 'name' => 'Nitro packs', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 50),
0x090300 => array('desc' => 'builds a Nuke', 'name' => 'Tactical nuke', 'type' => SC2_TYPEBUILDINGUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090400 => array('desc' => 'researches Stim pack', 'name' => 'Stim pack', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090401 => array('desc' => 'researches Combat shield', 'name' => 'Combat shield', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090402 => array('desc' => 'researches Concussive Shells', 'name' => 'Concussive shells', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 50),
0x090500 => array('desc' => 'researches Siege tech', 'name' => 'Siege tech', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090501 => array('desc' => 'researches Infernal pre-igniter', 'name' => 'Infernal pre-igniter', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x090502 => array('desc' => 'researches 250mm strike cannons', 'name' => '250mm strike cannons', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x090600 => array('desc' => 'researches Cloaking field', 'name' => 'Cloaking field', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 200, 'gas' => 200),
0x090602 => array('desc' => 'researches Caduceus reactor', 'name' => 'Caduceus reactor', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090603 => array('desc' => 'researches Corvid reactor', 'name' => 'Corvid reactor', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x090606 => array('desc' => 'researches Seeker missile', 'name' => 'Seeker missile', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x090607 => array('desc' => 'researches Durable materials', 'name' => 'Durable materials', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x090700 => array('desc' => 'researches Personal cloaking', 'name' => 'Personal cloaking', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x090701 => array('desc' => 'researches Moebius reactor', 'name' => 'Moebius reactor', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090802 => array('desc' => 'researches Vehicle plating level 1', 'name' => 'Vehicle plating L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090803 => array('desc' => 'researches Vehicle plating level 2', 'name' => 'Vehicle plating L2', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 175, 'gas' => 175),
0x090804 => array('desc' => 'researches Vehicle plating level 3', 'name' => 'Vehicle plating L3', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 250, 'gas' => 250),
0x090805 => array('desc' => 'researches Vehicle weapons level 1', 'name' => 'Vehicle weapons L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x090806 => array('desc' => 'researches Vehicle weapons level 2', 'name' => 'Vehicle weapons L2', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 175, 'gas' => 175),
0x090807 => array('desc' => 'researches Vehicle weapons level 3', 'name' => 'Vehicle weapons L3', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 250, 'gas' => 250),
0x090808 => array('desc' => 'researches Ship plating level 1', 'name' => 'Ship plating L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x090809 => array('desc' => 'researches Ship plating level 2', 'name' => 'Ship plating L2', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 225, 'gas' => 225),
0x09080A => array('desc' => 'researches Ship plating level 3', 'name' => 'Ship plating L3', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 300, 'gas' => 300),
0x09080B => array('desc' => 'researches Ship weapons level 1', 'name' => 'Ship weapons L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x09080C => array('desc' => 'researches Ship weapons level 2', 'name' => 'Ship weapons L2', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 175, 'gas' => 175),
0x09080D => array('desc' => 'researches Ship weapons level 3', 'name' => 'Ship weapons L3', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 250, 'gas' => 250),
0x0C0D00 => array('desc' => 'upgrades to Planetary fortress', 'name' => 'Planetary fortress', 'type' => SC2_TYPEBUILDINGUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x0D0300 => array('desc' => 'upgrades to Orbital command', 'name' => 'Orbital command', 'type' => SC2_TYPEBUILDINGUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 0),
0x0D0600 => array('desc' => 'lifts off (Orbital command)', 'name' => 'liftoff (Orbital command)', 'type' => SC2_TYPEABILITY),
0x0D0700 => array('desc' => 'lands (Orbital command)', 'name' => 'land (Orbital command)', 'type' => SC2_TYPEABILITY),
0x0D0B00 => array('desc' => 'researches Weapon refit', 'name' => 'Weapon refit', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x0D0B01 => array('desc' => 'researches Behemoth reactor', 'name' => 'Behemoth reactor', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x0D0E00 => array('desc' => 'uses Tactical nuclear strike (Ghost)', 'name' => 'Tactical nuclear strike (Ghost)', 'type' => SC2_TYPEABILITY),
0x0E0000 => array('desc' => 'salvages a Bunker', 'name' => 'salvage (Bunker)', 'type' => SC2_TYPEABILITY),
0x0E0100 => array('desc' => 'uses EMP round (Ghost)', 'name' => 'EMP round (Ghost)', 'type' => SC2_TYPEABILITY),
0x0F0A00 => array('desc' => 'builds auto-turret (Raven)', 'name' => 'Auto-turret (Raven)', 'type' => SC2_TYPEABILITY),

// protoss

0x050900 => array('desc' => 'sets rally point (Nexus)', 'name' => 'rally point (Nexus)', 'type' => SC2_TYPEGEN),
0x060400 => array('desc' => 'uses Chrono boost (Nexus)', 'name' => 'Chrono boost (Nexus)', 'type' => SC2_TYPEABILITY),
0x090900 => array('desc' => 'builds a Nexus', 'name' => 'Nexus', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 400, 'gas' => 0),
0x090901 => array('desc' => 'builds a Pylon', 'name' => 'Pylon', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 0),
0x090902 => array('desc' => 'builds an Assimilator', 'name' => 'Assimilator', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 75, 'gas' => 0),
0x090903 => array('desc' => 'builds a Gateway', 'name' => 'Gateway', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 0),
0x090904 => array('desc' => 'builds a Forge', 'name' => 'Forge', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 0),
0x090906 => array('desc' => 'builds a Twilight council', 'name' => 'Twilight council', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 100),
0x090907 => array('desc' => 'builds a Photon cannon', 'name' => 'Photon cannon', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 0),
0x090909 => array('desc' => 'builds a Stargate', 'name' => 'Stargate', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x09090A => array('desc' => 'builds Templar archives', 'name' => 'Templar archives', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 200),
0x09090B => array('desc' => 'builds Dark shrine', 'name' => 'Dark shrine', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 250),
0x09090D => array('desc' => 'builds a Robotics facility', 'name' => 'Robotics facility', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 200, 'gas' => 100),
0x09090E => array('desc' => 'builds a Cybernetics core', 'name' => 'Cybernetics core', 'type' => SC2_TYPEBUILDING, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 0),
0x090B00 => array('desc' => 'trains a Zealot', 'name' => 'Zealot', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 0, 'sup' => 2),
0x090B01 => array('desc' => 'trains a Stalker', 'name' => 'Stalker', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 125, 'gas' => 50, 'sup' => 2),
0x090B05 => array('desc' => 'trains a Sentry', 'name' => 'Sentry', 'type' => SC2_TYPEUNIT, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 100, 'sup' => 2),
0x090E00 => array('desc' => 'trains a Probe', 'name' => 'Probe', 'type' => SC2_TYPEWORKER, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 0, 'sup' => 1),
0x0A0300 => array('desc' => 'researches Ground weapons level 1', 'name' => 'Ground weapons L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x0A0301 => array('desc' => 'researches Ground weapons level 2', 'name' => 'Ground weapons L2', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 175, 'gas' => 175),
0x0A0303 => array('desc' => 'researches Ground armor level 1', 'name' => 'Ground weapons L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x0A0306 => array('desc' => 'researches Shields level 1', 'name' => 'Shields L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 200, 'gas' => 200),
0x0D0C00 => array('desc' => 'researches Air weapons level 1', 'name' => 'Air weapons L1', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x0D0C06 => array('desc' => 'researches Warp gate', 'name' => 'Warp gate', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 50, 'gas' => 50),
0x0D0C09 => array('desc' => 'researches Hallucination', 'name' => 'Hallucination', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 100, 'gas' => 100),
0x0D0D00 => array('desc' => 'researches Charge', 'name' => 'Charge', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 200, 'gas' => 200),
0x0D0D01 => array('desc' => 'researches Blink', 'name' => 'Blink', 'type' => SC2_TYPEUPGRADE, 'subtype' => SC2_SUBTYPECREATE, 'min' => 150, 'gas' => 150),
0x0D0400 => array('desc' => 'transforms to Warp gate (Gateway)', 'name' => 'transform to Warp gate (Gateway)', 'type' => SC2_TYPEABILITY),


// generic

0x020400 => array('desc' => 'stops', 'name' => 'stop', 'type' => SC2_TYPEGEN),
0x020601 => array('desc' => 'patrols', 'name' => 'patrol', 'type' => SC2_TYPEGEN),
0x020602 => array('desc' => 'holds position', 'name' => 'hold position', 'type' => SC2_TYPEGEN),
0x020900 => array('desc' => 'attacks', 'name' => 'attack', 'type' => SC2_TYPEGEN),
0xFFFF0F => array('desc' => 'right-clicks', 'name' => 'right-click', 'type' => SC2_TYPEGEN)
);


?>