## General ##

This file contains all events that happened in the game. This includes things like right-clicks, move orders, attack orders, selection changes, build orders and the like.

## Limitations with parsing ##

The event system is innately married to the game engine. What this means is that for example unit deaths, hit points and resources can't be directly parsed. A similar limitation exists for interpreting build orders. As a simple example, let's assume at game start that a player clicks the "Build SCV" button 3 times. Even though two of the three clicks will fail because of lack of resources, all 3 will get registered as "builds an SCV" in this file. In order to distinguish between failed build orders, one would have to implement at least a part of the game engine to keep track of different resources. This is, however, completely unfeasible and thus any build order lists will always contain errors.

## File format ##

The file contains all events one after another. Each event is defined by event header and may have an arbitrary number of data based on the event type.<br />
Note: some events, even though documented here, may not exist at all either due to changes in the replay format or brain errors.

#### Event header ####
| **Explanation** | **Number of bytes** | **Extra information** |
|:----------------|:--------------------|:----------------------|
| Frames since last event<br />(divide by 16 to get seconds) | 1-4 bytes | See [Timestamp format](ReplayGameEvents#Timestamp_format.md) |
| Event type | 3 lowest(rightmost) bits |  |
| Global event | 4th lowest bit | 1 for true, 0 for false |
| Player ID | bits 5-8 | Event type, Global event and Player ID are contained in the same byte |
| Event code | 1 byte |  |
| Event data | N bytes | Variable length, depends on event type and event code |

#### Known events and event data lengths ####
| **Event type** | **Event code** | **Explanation** | **Event data length** |
|:---------------|:---------------|:----------------|:----------------------|
| 0 | 0x0B | Player enters game | 0 bytes |
| 0 | 0x1B | Player enters game | 0 bytes |
| 0 | 0x05 | Game starts | 0 bytes |
| 1 | 0x09 | Player quits game | 0 bytes |
| 1 | 0x0B | Player uses an ability | 32 bytes, 1 extra byte if 8th data byte is 0x30 or 0x50 |
| 1 | 0x1F-0x8F(second half-byte always F) | Player sends resources to ally | 17 bytes, see [Resource transfer format](ReplayGameEvents#Resource_transfer_format.md) |
| 1 | 0x0C-0xAC(second half-byte always C) | Selection update | N bytes, see [Selection event format](ReplayGameEvents#Selection_event_format.md) |
| 1 | 0x0D-0x9D(second half-byte always D) | Uses hotkey or assigns selection to hotkey | See [Hotkey event format](ReplayGameEvents#Hotkey_event_format.md) |
| 2 | 0x06 | Unknown | 8 bytes |
| 3 | 0x01 - 0xF1(second half-byte always 1) | Screen movement | See [Screen movement format](ReplayGameEvents#Screen_movement_format.md) |
| 4 | 0x00 | Automatic synchronization | 4 bytes |
| 4 | 0x16 | Unknown | 24 bytes |
| 4 | 0x18 | Unknown | 4 bytes |
| 4 | 0x1C | Unknown | 0 bytes |
| 4 | 0x2C | Unknown | 0 bytes |
| 5 | 0x89 | Automatic synchronization? | 4 bytes |


#### Timestamp format ####
Timestamps are 1-4 bytes long and represent a number of frames. Usually it is time elapsed since the last event. A frame is 1/16th of a second. The least significant 2 bits of the first byte specify how many extra bytes the timestamp has.<br />

Example: assume we have the bytes 0xC5 0xA3, whose bit representation is the following:

| 0xC5<br />110001<u>01</u> | 0xA3<br />10100011 |
|:--------------------------|:-------------------|

The underlined bits are the ones specifying the number of extra bytes. 01 is 1 extra byte, 10 would be 2 extra bytes and 11 3 extra bytes. They are eliminated from the actual number. The resulting bits from this example would then be 110001 10100011.

#### Selection event format ####

The selection event contains information about what units were selected or deselected by a player.  Parsing them is difficult due to the Deselection Map which is n-<em>bits</em> long causing the remainder of the selections to be offset by n-bits.  If the deselection map has a length that is a multiple of 8 then the entire selection event is byte aligned making parsing the simplest case:

| <b>Flags</b> | 1 byte |
|:-------------|:-------|
| <b>Deselected Units Count </b> | 1 byte |
| <b>Deselected Units Map </b> | n <em>bits</em> |
| <b>Number of Added Unit Types</b> = X | 1 byte |
| <b>Type 1 ID</b> | 3 bytes |
| <b>Type 1 Count</b> | 1 byte |
| <b>Type 2 ID</b> | 3 bytes |
| <b>Type 2 Count</b> | 1 byte |
| ... |  |
| <b>Type X ID</b> | 3 bytes |
| <b>Type X Count</b> | 1 byte |
| <b>Total Number of Units Added</b> = K | 1 byte |
| <b>Unit 1 ID</b> | 4 bytes |
| <b>Unit 2 ID</b> | 4 bytes |
| ... |  |
| <b>Unit K ID</b> | 4 bytes |

Parsing this is quite simple when there is no bit-shift.  However the way bit-shift is accounted for is somewhat odd, and non-standard even within the file.

<b><em>Standard Bit-Shift Accounting</em></b><br />
For the simple case assume that the bit-shift is 0 meaning that the number of bits in the deselection map is a multiple of 8.  In this case there is no bit shift and each byte can be read directly.
Example:

| HEX | Binary | Description of line |
|:----|:-------|:--------------------|
| 00  | 0000 0000 | Flags |
| 00  | 0000 0000 | # of units deselected = 0 |
|     |           | No Deselection Map |
| 01  | 0000 0001 | Of units added there is one type |
| 00  | 0000 0000 | Unit Type 1 ID (Probe) Byte1 |
| 72  | 0111 0010 | ... Byte2 |
| 01  | 0000 0001 | ... Byte3 |
| 02  | 0000 0010 | 6 Units of Type 1 (Probe) Added |
| 02  | 0000 0010 | 6 Total Units Added |
| 01  | 0000 0001 | Unit 1 ID Byte 1 |
| 84  | 1000 0100 | Unit 1 ID Byte 2 |
| 00  | 0000 0000 | Unit 1 ID Byte 3 (flag) |
| 00  | 0000 0000 | Unit 1 ID Byte 4 (flag) |
| 01  | 0000 0001 | Unit 2 ID Byte 1 |
| 88  | 1000 1000 | Unit 2 ID Byte 2 |
| 00  | 0000 0000 | Unit 2 ID Byte 3 (flag) |
| 00  | 0000 0000 | Unit 2 ID Byte 4 (flag) |

However, in the following example assume that there is a bit-shift of 3.  Then to read each byte you need to generate the compound of two read bytes.  The way that the current replay files do the bit shift <b>for single byte blocks</b> is as follows:
| Byte 1 | Byte 2 | Byte 3 | Byte 4 | Byte 5 |
|:-------|:-------|:-------|:-------|:-------|
|00000011|BBBBBAAA|CCCCCbbb|DDDDDccc|00000ddd|

5 Bytes Read to Yield:
|Map Count| Map | Next | Next | Final|
|:--------|:----|:-----|:-----|:-----|
|000000011| AAA | BBBBBbbb | CCCCCccc | DDDDDddd |

The first byte shows the offset size. Its read directly.  Then the map is read which is the final 3 bits of the next byte.  The 'head' or first 5 bits of the byte becomes the first 5 bits of the computed byte, and the 'tail' or final 3 bits are read from the end of the following byte.

When there is a bit-offset there is always a trailing byte that is 0-padded with the final X bits needed to complete the data (for the above example: 00000ddd).

<b>Multi-byte blocks</b> are slightly different in the way they're read.  For instance reading the ID of each unit (the last K 4-byte blocks) The way they are read is "wrapped" like this:

These are three different offset encodings of the same 3-byte block:
![![](http://imgur.com/FNJfgl.jpg)](http://imgur.com/FNJfg.png)

This is essentially wrapping.  When the byte after offset is too long for the line it is continued on the following line with the least significant bits from the previous byte being the most significant in the current line.


<em><b>Type ID</b></em><br />
Now there is one multi-byte block that is read differently: the Unit Type ID.  This is read differently in that it mixes line-wrapping with the traditional end-of-line substitution.  Here is an example of 3 different encodings of the same Unit Type ID:

![![](http://imgur.com/NsNjMl.jpg)](http://imgur.com/NsNjM.png)

Note how when there is an offset the first byte is wrapped in that the least significant bits are the first bits of the following line.  For the second byte the least significant bits it uses the tail of the second line as the most significant bits and then uses the tail of the following line as usual, and the final bit follows the conventions of a usual bit-shifted byte read.

### Unit Ids ###
Unit IDs are assigned as needed (when unit is complete) by the game engine.

They're broken up into 2 2-byte sections:
| UniqueID | Tag Data |
|:---------|:---------|
| 01 84    | 00 01    |

The UniqueID auto increments by 4 (the fact that this is 4 makes me believe that the last two bits are being used for something else).  For instance the first things placed (such as mineral patches, xel'naga towers and rocks) receive the lowest ids starting from 0x00)

Example from blistering sands:
| 0x0000 | Destructible Rocks |
|:-------|:-------------------|
| 0x0004 | Destructible Rocks |
| 0x0008 | High Yield Minerals|
| 0x000C | High Yield Minerals|
| ...    |
| 0x0020 | High Yield Minerals|
| 0x0024 | High Yield Minerals|
| 0x0028 | Vespine Geyser |
| ...    |
| 0x003C | Vespine Geyser |
| 0x0040 | Xel'naga Tower |
| 0x0044 | Xel'naga Tower |

And so on until we get to the player units:
| 0x0180 | Player 1 Nexus |
|:-------|:---------------|
| 0x0184 | Player 1 Probe |
| 0x0188 | Player 1 Probe |
| 0x018C | Player 1 Probe |
| 0x0190 | Player 1 Probe |
| 0x0194 | Player 1 Probe |
| 0x0198 | Player 1 Probe |
| 0x019C | Player 2 Command Center |
| 0x01A0 | Player 2 SCV |
| 0x01A4 | Player 2 SCV |
| 0x01A8 | Player 2 SCV |
| 0x01AC | Player 2 SCV |
| 0x01B0 | Player 2 SCV |
| 0x01B4 | Player 2 SCV |

This then is the state for the start of the game.
The first unit that is created next (Finished building) is then given ID
| 0x01B8 |
|:-------|

This assumes that no assigned IDs have been freed.  For example if one of the starting probes were killed its ID would become free to be used by a new unit.  Again the ID is grabbed on completion of a unit, so a unit build started before the ID was free'd can still claim the free ID.  The ID assigned is the smallest available ID, if none are available then the ID given is 4 greater than the largest assigned ID.

You'll notice I've only been showing the first 2 bytes of a Unit ID.  There are 2 trailing bytes for Unit ID.  These two bytes store the number of times that this ID has been assigned.  To continue the example above, if a probe was killed and free'd its ID of 0x180 and the opponent was the first to make a unit (an SCV) after the death of that probe, then the new SCV would receive ID 0x0180 instead of the incremented (0x01B8).  Additionally the full ID of the new SCV would be
|0x0180 | 0x0002 |
|:------|:-------|

Indicating that ID 0x0180 has been assigned to 2 different units.  It proceeds to be incremented every time that it is given to a new unit.

<em>NOTES</em>
Natural upgrades like Spire `->` Greater Spire do not cause re-assigns even though they share UniqueID.  Nor do Burrow `<->` Unburrow or Fly `<->` Land.  Oddly enough, zerg buildings do <b>not</b> claim the ID of the drone that was sacrificed to create them.  I do not know if thats because the unit is refunded on cancel so its stored in limbo, but it simply doesn't happen.

Unfortunately, currently there is no way to tell whose units are selected (aside from being unable to select multiples of opponents units).  This is apparently handled in engine somewhere.

#### Hotkey event format ####

On todo-list

#### Resource transfer format ####

On todo-list

#### Screen movement format ####

On todo-list