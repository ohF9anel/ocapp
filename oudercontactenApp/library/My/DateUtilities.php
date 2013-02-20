<?php
class DateUtilities
{
    public static function weekday ($date, $short = false) {
        switch (date('N', $date)) {
            case 1: return ($short ? 'ma' : 'maandag');
            case 2: return ($short ? 'di' : 'dinsdag');
            case 3: return ($short ? 'woe' : 'woensdag');
            case 4: return ($short ? 'do' : 'donderdag');
            case 5: return ($short ? 'vr' : 'vrijdag');
            case 6: return ($short ? 'za' : 'zaterdag');
            case 7: return ($short ? 'zo' : 'zondag');
        }
    }
    
    public static function month ($date, $short = false) {
        switch (date('n', $date)) {
            case 1: return ($short ? 'jan' : 'januari');
            case 2: return ($short ? 'feb' : 'februari');
            case 3: return ($short ? 'mrt' : 'maart');
            case 4: return ($short ? 'apr' : 'april');
            case 5: return 'mei';
            case 6: return ($short ? 'jun' : 'juni');
            case 7: return ($short ? 'jul' : 'juli');
            case 8: return ($short ? 'aug' : 'augustus');
            case 9: return ($short ? 'sep' : 'september');
            case 10: return ($short ? 'okt' : 'oktober');
            case 11: return ($short ? 'nov' : 'november');
            case 12: return ($short ? 'dec' : 'december');
        }
    }
}
