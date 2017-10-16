<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Dav;

use OCA\DAV\CalDAV\ICalendarHomePlugin;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class DeckDavPlugin extends ServerPlugin implements ICalendarHomePlugin {

	/**
	 * @inheritdoc
	 * @param Server $server
	 */
	public function initialize(Server $server) {

	}

	/**
	 * Make sure our calendar endpoint is exposed in the calendar-home-set
	 *
	 * @inheritdoc
	 */
	public function getCalendarHomeForPrincipal($principalUrl) {

		if (strrpos($principalUrl, 'principals/users', -strlen($principalUrl)) !== false) {
			list(, $principalId) = \Sabre\Uri\split($principalUrl);
			return 'deck/' . $principalId;
		}

		return null;
	}


}