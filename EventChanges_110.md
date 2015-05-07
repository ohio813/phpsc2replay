# Selection event #

The selection event (or event code 0x1C to 0xAC) was changed in patch 1.10.

| **Byte** | **Explanation** |
|:---------|:----------------|
| <flag byte> | Values of 0x00 and 0x01 have been seen |
| <deselection flag byte> | Low 2 bits specify the deselection type and bitshifts (see below).<br />Any bitshifts will propagate to the rest of the data. |
| <deselection bits> 0-N bytes? | the amount depends on the deselection flag byte. |
| <number of unit type IDs> 1 byte | Number of unit type IDs |
| <unit type ID> 3 bytes, <number of this type of units> 1 byte | Total of 4 bytes,<br />repeated for each unit ID |
| <total number of units> 1 byte | Total number of unit IDs |
| unit IDs, 4\*N bytes | 4 bytes per unit ID |

The low 2 bits of the deselection byte will specify the deselection bits length as follows:
  * 0x00: No deselection and a bit shift of 2 bits for the entire event.
  * 0x01: Deselect some units. Number of deselection bits is (<deselection flag byte> & 0xFC) | (<following byte> & 3). Bit shift is 2 + <number of deselection bits> mod 8
  * 0x02: Deselect some units. Number of deselection **bytes** is (<deselection flag byte> & 0xFC) | (<following byte> & 3). Bit shift is 2.
  * 0x03: Deselect all units, bit shift of 2.

Special thanks to jeanmarc.soumet for figuring it out.

# Action event #

This event is the 0x0B event.<br />

This event was also seemingly redesigned and is variable length based on the first bytes of the event. Not much time has been dedicated to the event yet, but the following seems to give correct event lengths:

| **Byte** | **Explanation** |
|:---------|:----------------|
| unknown | seems to have some effect on the event length if the second byte is 0x20 or 0x22 |
| <flag byte?> | If the byte has bit 0x20 set, the length depends on the previous byte as well as the last byte of the ability code.<br /> If bit 0x40 or 0x80 is set, the event seems to be fixed-length (7 and 15 extra bytes after ability code, respectively). |
| <ability code> 3 bytes | self-explanatory |
| <unknown bytes> 0, 4, 9, 10 or 18 | number of bytes depends on flag byte, the first unknown byte of the event and the last byte of the ability code. |

jeanmarc.soumet has contributed the following regarding ability codes:
```
It seems that you have to do a couple things to translate to the old ability:
bit1_new = (bit1 << 2) + (bit2 & 0x30) >> 4
bit2_new = (bit2 & 0x0F)
bit3_new = (bit3 & 0x0F)

bit3_new & 0xF0 seems to be a flag of some kind but i haven't figured it out.

I am not 100% sure about this but I verified with a few units of protoss and terran and it works.
```

# Group event #

The group event (0x0D to 0x9D) seems to have also been changed (Whenever a player assigns units to a group or selects a group by pressing 1-9).

| **Byte** | **Explanation** |
|:---------|:----------------|
| <flag byte> | Flags (see below) |
| <deselection mask> | Length depends on the flag byte |

  * The lowest 2 bits of the flag byte define the type of group event. 0x00 means clear group, 0x01 means adding current selection to group and 0x02 means selecting group.
  * The next 2 bits specify how the deselection mask length is calculated. If bit 3 is set(0x04), the deselection bit length is calculated in a way similar to the 0x01 deselection flag in the action event.
  * If bit 4 is set (0x08), the deselection bit length is calculated like the 0x02 deselection flag in the action event.