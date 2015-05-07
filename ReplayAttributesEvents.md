## Introduction ##

This file contains various game settings, such as game speed, player color indices, etc.

## File format ##

| Explanation | Number of bytes | Extra information |
|:------------|:----------------|:------------------|
| File header | 4 |  |
| Number of attributes | 4 | little-endian byte order |
| X attributes(x is number of attributes above | 13\*X | See [Attribute format](ReplayAttributesEvents#Attribute_format.md) below |

## Attribute format ##

Many attribute values are in plain-text and due to the value being little endian like most other multi-byte values, the values are in reverse in the file (for example torP for Prot and so on).<br />
Player ID of 0x10 (16 decimal) indicates that the value is not specific to any player.

| Explanation | Number of bytes | Extra information |
|:------------|:----------------|:------------------|
| Attribute header | 4 | little endian (always 0xe7 0x03 0x00 0x00?) |
| Attribute ID | 4 | little endian |
| Player ID | 1 |  |
| Attribute value | 4 | little endian |

## Known attributes ##
The attribute IDs are in little endian byte order inside the file, while in this list they are in big endian. As an example, the value of 0x07D2 in this list would correspond to the bytes 0xD2 0x07 0x00 0x00 inside the file. <br />The same goes for data values. Any data values are also zero-padded so that they are always 4 bytes.


| Attribute ID | Explanation | Data meaning |
|:-------------|:------------|:-------------|
| 0x01F4 | Player type | "Humn" for a human player, "Comp" for computer |
| 0x07D1 | Team size | "1v1", "2v2", "3v3", "4v4" or "FFA" |
| 0x07D2 | Player teams for 1v1 | "T1", "T2", ... , "T8" |
| 0x07D3 | Player teams for 2v2 | "T1", "T2", ... , "T8" |
| 0x07D4 | Player teams for 3v3 | "T1", "T2", ... , "T8" |
| 0x07D5 | Player teams for 4v4 | "T1", "T2", ... , "T8" |
| 0x07D6 | Player teams for FFA | "T1", "T2", ... , "T8" |
| 0x0BB8 | Game speed | self-explanatory<br />In order: "Slor", "Slow", "Norm", "Fast", "Fasr" |
| 0x0BB9 | Race | self-explanatory, "Zerg", "Prot", "Terr" or "Rand" |
| 0x0BBA | Player color index | "tc01", "tc02", ... , "tc08"<br />This value indicates the color chosen in the dropdown box.<br />The values are (from tc01 to tc08):<br />Red, Blue, Teal, Purple, Yellow, Orange, Green, Pink |
| 0x0BBB | Player handicap | 50-100, signifying the percentage |
| 0x0BBC | Difficulty level | self-explanatory, "Medi" for human players<br />In order: "VyEy", "Easy", "Medi", "Hard", "VyHd", "Insa" |
| 0x0BC1 | Game type, private or open | "Priv" for private, "Amm" for open? |