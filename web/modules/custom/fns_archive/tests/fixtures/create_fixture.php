<?php

/**
 * Creates a minimal JPEG with GPS EXIF data for testing.
 *
 * GPS: 35°40'52.32"N, 139°46'1.56"E (Tokyo area)
 * Decimal: lat=35.681200, lon=139.767100
 *
 * Run: ddev exec php /var/www/html/web/modules/custom/fns_archive/tests/fixtures/create_fixture.php
 */

/**
 * Pack a rational (numerator/denominator) as two LONG (4-byte) values.
 */
function pack_rational(int $num, int $den): string {
  return pack('VV', $num, $den);
}

/**
 * Convert decimal degrees to DMS rationals.
 * Returns array of [deg_num, deg_den, min_num, min_den, sec_num, sec_den].
 */
function decimal_to_dms_rationals(float $decimal): array {
  $decimal = abs($decimal);
  $deg = (int) $decimal;
  $min_float = ($decimal - $deg) * 60;
  $min = (int) $min_float;
  $sec_float = ($min_float - $min) * 60;
  // Store seconds as rational with denominator 100.
  $sec_num = (int) round($sec_float * 100);
  return [$deg, 1, $min, 1, $sec_num, 100];
}

$lat = 35.6812;
$lon = 139.7671;

[$lat_deg_n, $lat_deg_d, $lat_min_n, $lat_min_d, $lat_sec_n, $lat_sec_d] = decimal_to_dms_rationals($lat);
[$lon_deg_n, $lon_deg_d, $lon_min_n, $lon_min_d, $lon_sec_n, $lon_sec_d] = decimal_to_dms_rationals($lon);

// Build GPS IFD data (little-endian).
// GPS tags:
//   0x0001 GPSLatitudeRef  ASCII 2 bytes "N\0"
//   0x0002 GPSLatitude     RATIONAL 3 values
//   0x0003 GPSLongitudeRef ASCII 2 bytes "E\0"
//   0x0004 GPSLongitude    RATIONAL 3 values

// IFD entry: tag(2) + type(2) + count(4) + value_offset(4) = 12 bytes each
// 4 entries + next IFD offset (4 bytes) + value data

$num_entries = 4;
$ifd_header_size = 2 + ($num_entries * 12) + 4; // entry count + entries + next IFD

// Value data offsets are relative to start of TIFF header (8 bytes into APP1).
// TIFF header = 8 bytes, IFD starts at offset 8.
// IFD size = 2 + 4*12 + 4 = 54 bytes
// Value data starts at offset 8 + 54 = 62

$tiff_header_size = 8;
$ifd_size = 2 + ($num_entries * 12) + 4;
$value_data_offset = $tiff_header_size + $ifd_size; // = 62

// GPSLatitudeRef: ASCII, 2 bytes, fits in value field directly.
// GPSLatitude: RATIONAL, 3 values = 24 bytes, stored at value_data_offset.
// GPSLongitudeRef: ASCII, 2 bytes, fits in value field directly.
// GPSLongitude: RATIONAL, 3 values = 24 bytes, stored at value_data_offset + 24.

$lat_data_offset = $value_data_offset;       // 62
$lon_data_offset = $value_data_offset + 24;  // 86

// Build IFD entries (little-endian).
$ifd  = pack('v', $num_entries); // entry count

// 0x0001 GPSLatitudeRef: type=ASCII(2), count=2, value="N\0" padded to 4 bytes
$ifd .= pack('vvV', 0x0001, 2, 2) . "N\0\0\0";

// 0x0002 GPSLatitude: type=RATIONAL(5), count=3, offset
$ifd .= pack('vvVV', 0x0002, 5, 3, $lat_data_offset);

// 0x0003 GPSLongitudeRef: type=ASCII(2), count=2, value="E\0" padded to 4 bytes
$ifd .= pack('vvV', 0x0003, 2, 2) . "E\0\0\0";

// 0x0004 GPSLongitude: type=RATIONAL(5), count=3, offset
$ifd .= pack('vvVV', 0x0004, 5, 3, $lon_data_offset);

$ifd .= pack('V', 0); // next IFD offset = 0

// Value data.
$value_data  = pack_rational($lat_deg_n, $lat_deg_d);
$value_data .= pack_rational($lat_min_n, $lat_min_d);
$value_data .= pack_rational($lat_sec_n, $lat_sec_d);
$value_data .= pack_rational($lon_deg_n, $lon_deg_d);
$value_data .= pack_rational($lon_min_n, $lon_min_d);
$value_data .= pack_rational($lon_sec_n, $lon_sec_d);

// TIFF header (little-endian): "II" + 0x002A + offset to first IFD (8).
$tiff_header = "II" . pack('v', 0x002A) . pack('V', 8);

// GPS IFD block = tiff_header + ifd + value_data.
$gps_ifd_block = $tiff_header . $ifd . $value_data;

// Wrap in Exif APP1 marker.
// APP1 structure: FF E1 + length(2) + "Exif\0\0" + TIFF data
// But we need a proper EXIF IFD0 pointing to GPS sub-IFD.
// Simpler: use a real minimal EXIF with IFD0 containing only GPSInfo tag (0x8825).

// Rebuild properly with IFD0 -> GPS sub-IFD.
// IFD0: 1 entry (GPSInfo pointer), then GPS IFD data.

$num_ifd0_entries = 1;
$ifd0_size = 2 + ($num_ifd0_entries * 12) + 4; // 18 bytes
$gps_ifd_offset = $tiff_header_size + $ifd0_size; // 8 + 18 = 26

// GPS IFD at offset 26.
$gps_ifd_value_data_offset = $gps_ifd_offset + $ifd_size; // 26 + 54 = 80

$lat_data_offset2 = $gps_ifd_value_data_offset;       // 80
$lon_data_offset2 = $gps_ifd_value_data_offset + 24;  // 104

// IFD0.
$ifd0  = pack('v', $num_ifd0_entries);
// 0x8825 GPSInfo: type=LONG(4), count=1, value=offset to GPS IFD
$ifd0 .= pack('vvVV', 0x8825, 4, 1, $gps_ifd_offset);
$ifd0 .= pack('V', 0); // next IFD = 0

// GPS IFD.
$gps_ifd2  = pack('v', $num_entries);
$gps_ifd2 .= pack('vvV', 0x0001, 2, 2) . "N\0\0\0";
$gps_ifd2 .= pack('vvVV', 0x0002, 5, 3, $lat_data_offset2);
$gps_ifd2 .= pack('vvV', 0x0003, 2, 2) . "E\0\0\0";
$gps_ifd2 .= pack('vvVV', 0x0004, 5, 3, $lon_data_offset2);
$gps_ifd2 .= pack('V', 0);

// Value data.
$vdata  = pack_rational($lat_deg_n, $lat_deg_d);
$vdata .= pack_rational($lat_min_n, $lat_min_d);
$vdata .= pack_rational($lat_sec_n, $lat_sec_d);
$vdata .= pack_rational($lon_deg_n, $lon_deg_d);
$vdata .= pack_rational($lon_min_n, $lon_min_d);
$vdata .= pack_rational($lon_sec_n, $lon_sec_d);

$tiff = "II" . pack('v', 0x002A) . pack('V', 8) . $ifd0 . $gps_ifd2 . $vdata;

$exif_header = "Exif\0\0";
$app1_data = $exif_header . $tiff;
$app1_length = strlen($app1_data) + 2; // +2 for length field itself
$app1 = "\xFF\xE1" . pack('n', $app1_length) . $app1_data;

// Minimal 1x1 white JPEG body (SOI + APP0 + DQT + SOF0 + DHT + SOS + EOI).
// Use a known-good minimal JPEG and prepend our APP1 after SOI.
$minimal_jpeg_body = "\xFF\xD8" // SOI
  . $app1
  // APP0 JFIF
  . "\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
  // Minimal DQT (64 bytes of value 16)
  . "\xFF\xDB\x00\x43\x00" . str_repeat("\x10", 64)
  // SOF0: 1x1 grayscale
  . "\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00"
  // DHT (minimal Huffman table)
  . "\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B"
  // SOS
  . "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xF8"
  // EOI
  . "\xFF\xD9";

$output = __DIR__ . '/gps_tagged.jpg';
file_put_contents($output, $minimal_jpeg_body);
echo "Created: $output\n";

// Verify.
$exif_check = @exif_read_data($output);
if ($exif_check && isset($exif_check['GPSLatitude'])) {
  echo "GPS verified: lat=" . implode(',', $exif_check['GPSLatitude']) . " ref=" . $exif_check['GPSLatitudeRef'] . "\n";
}
else {
  echo "WARNING: GPS not found in output file\n";
  var_dump($exif_check);
}
