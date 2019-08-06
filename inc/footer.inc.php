<?php
/*  Poweradmin, a friendly web-based admin tool for PowerDNS.
 *  See <http://www.poweradmin.org> for more details.
 *
 *  Copyright 2007-2009  Rejo Zenger <rejo@zenger.nl>
 *  Copyright 2010-2017  Poweradmin Development Team
 *      <http://www.poweradmin.org/credits.html>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Web interface footer
 *
 * @package     Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2017  Poweradmin Development Team
 * @license     http://opensource.org/licenses/GPL-3.0 GPL
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

$VERSION = Poweradmin\Version::VERSION;

global $db;
if (is_object($db)) {
    $db->disconnect();
}
?>
</div> <!-- /content -->

<div class="footer">
    <a href="http://www.poweradmin.org/">a complete(r) <strong>poweradmin</strong><?php
        if (isset($_SESSION["userid"])) {
            echo " v$VERSION";
        }
        ?></a> - <a href="http://www.poweradmin.org/credits.html">credits</a>
</div>

<?php
if (file_exists('inc/custom_footer.inc.php')) {
    include('inc/custom_footer.inc.php');
}

if (isset($db_debug) && $db_debug == true) {
    $lines = $db->getDebugOutput();

    foreach ($lines as $line) {
        echo "<p>$line</p>";
    }
}

global $display_stats;
if ($display_stats)
    display_current_stats();
?>

</body>
</html>
