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

use OCA\Deck\Db\Card;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\StackService;
use Sabre\CalDAV\Plugin;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\VObject;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Service\BoardService;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\Uri;

class CalDavBackend extends AbstractBackend implements SyncSupport {

	private $principalBackend;
	private $app;
	private $legacyEndpoint;
	private $groupManager;
	private $userManager;
	private $boardService;
	private $stackService;
	private $cardService;


	public function __construct($userPrincipalBackend, Application $app, $legacyEndpoint = false) {
		$this->principalBackend = $userPrincipalBackend;
		$this->app = $app;
		$this->legacyEndpoint = $legacyEndpoint;

		$this->groupManager = $app->getContainer()->getServer()->getGroupManager();
		$this->userManager  =$app->getContainer()->getServer()->getUserManager();
		$this->boardService = $app->getContainer()->query(BoardService::class);
		$this->stackService = $this->app->getContainer()->query(StackService::class);
		$this->cardService = $app->getContainer()->query(CardService::class);
		$user = $app->getContainer()->getServer()->getUserSession()->getUser();
		if ($user !== null) {
			$this->userId = $user->getUID();
		}
	}

	private function boardToCalendar($board, $principalUri) {
		$ownerPrincipalUrl = 'principals/users/' . $board->getOwner();
		$this->legacyEndpoint = false;
		$calendar = [
			'id' => $board->getId(),
			'{DAV:}displayname' => $board->getTitle(),
			'uri' => (string)$board->getId(),
			'principaluri' => $this->convertPrincipal($ownerPrincipalUrl, !$this->legacyEndpoint),
			'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal' => $this->convertPrincipal($ownerPrincipalUrl, !$this->legacyEndpoint),
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT', 'VTODO']),
			'{http://apple.com/ns/ical/}calendar-color' => '#' . $board->getColor(),
			// FIXME: '{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',

		];
		// FIXME: see dav app CalDavBackend::addOwnerPrincipal
		return $calendar;
	}

	public function applyShareAcl($resourceId, $acl) {
		// FIXME: only readonly acl for current user when board is shared atm
		$acl[] = [
				'privilege' => '{DAV:}read',
				'principal' => $this->convertPrincipal('principals/users/1', !$this->legacyEndpoint),
				'protected' => true,
			];
		return $acl;
	}

	public function getShares($resourceId) {
		return [];
	}

	public function getPublishStatus($resourceId) {
		return [];
	}

	private function convertPrincipal($principalUri, $toV2) {
		if ($this->principalBackend->getPrincipalPrefix() === 'principals') {
			list(, $name) = Uri\split($principalUri);
			if ($toV2 === true) {
				return "principals/users/$name";
			}
			return "principals/$name";
		}
		return $principalUri;
	}

	/**
	 * Returns a list of calendars for a principal.
	 *
	 * Every project is an array with the following keys:
	 *  * id, a unique id that will be used by other functions to modify the
	 *    calendar. This can be the same as the uri or a database key.
	 *  * uri, which is the basename of the uri with which the calendar is
	 *    accessed.
	 *  * principaluri. The owner of the calendar. Almost always the same as
	 *    principalUri passed to this method.
	 *
	 * Furthermore it can contain webdav properties in clark notation. A very
	 * common one is '{DAV:}displayname'.
	 *
	 * Many clients also require:
	 * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
	 * For this property, you can just return an instance of
	 * Sabre\CalDAV\Property\SupportedCalendarComponentSet.
	 *
	 * If you return {http://sabredav.org/ns}read-only and set the value to 1,
	 * ACL will automatically be put in read-only mode.
	 *
	 * @param string $principalUri
	 * @return array
	 */
	public function getCalendarsForUser($principalUri) {
		$groups = $this->groupManager->getUserGroupIds(
			$this->userManager->get($this->userId)
		);
		$userInfo = [
			'user' => $this->userId,
			'groups' => $groups
		];
		$boards = $this->boardService->findAll($userInfo);
		$objects = [];
		foreach ($boards as $board) {
			$calendarInfo = $this->boardToCalendar($board, $principalUri);
			$objects[] = $calendarInfo;
		}
		return $objects;
	}


	/**
	 * Returns all calendar objects within a calendar.
	 *
	 * Every item contains an array with the following keys:
	 *   * calendardata - The iCalendar-compatible calendar data
	 *   * uri - a unique key which will be used to construct the uri. This can
	 *     be any arbitrary string, but making sure it ends with '.ics' is a
	 *     good idea. This is only the basename, or filename, not the full
	 *     path.
	 *   * lastmodified - a timestamp of the last modification time
	 *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
	 *   '"abcdef"')
	 *   * size - The size of the calendar objects, in bytes.
	 *   * component - optional, a string containing the type of object, such
	 *     as 'vevent' or 'vtodo'. If specified, this will be used to populate
	 *     the Content-Type header.
	 *
	 * Note that the etag is optional, but it's highly encouraged to return for
	 * speed reasons.
	 *
	 * The calendardata is also optional. If it's not returned
	 * 'getCalendarObject' will be called later, which *is* expected to return
	 * calendardata.
	 *
	 * If neither etag or size are specified, the calendardata will be
	 * used/fetched to determine these numbers. If both are specified the
	 * amount of times this is needed is reduced by a great degree.
	 *
	 * @param mixed $calendarId
	 * @return array
	 */
	public function getCalendarObjects($calendarId) {
		$result = [];

		/** @var StackService $stackService */
		$stacks = $this->stackService->findAll($calendarId);
		foreach ($stacks as $stack) {
			/** @var Card $card */
			foreach ($stack->getCards() as $card) {
				$vcalendar = new VObject\Component\VCalendar([
					'VTODO' => $card->getVtodo()
				]);
				$result[] = [
					'id' => $card->getId(),
					'uri' => $card->getId() . '.ics',
					'lastmodified' => $card->getLastModified(),
					'etag' => '"' . md5($card->getLastModified()) . '"',
					'calendarid' => $calendarId,
					'component' => 'vtodo',
					'size' => (int)strlen($vcalendar->serialize())
				];
			}
		}

		return $result;
	}

	/**
	 * Returns information from a single calendar object, based on it's object
	 * uri.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * The returned array must have the same keys as getCalendarObjects. The
	 * 'calendardata' object is required here though, while it's not required
	 * for getCalendarObjects.
	 *
	 * This method must return null if the object did not exist.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @return array|null
	 */
	public function getCalendarObject($calendarId, $objectUri) {
		/** @var StackService $stackService */
		$stackService = $this->app->getContainer()->query(StackService::class);
		$stacks = $stackService->findAll($calendarId);
		foreach ($stacks as $stack) {
			/** @var Card $card */
			foreach ($stack->getCards() as $card) {
				if ((string)$card->getId().'.ics' === $objectUri) {
					$vcalendar = new VObject\Component\VCalendar([
						'VTODO' => $card->getVtodo()
					]);
					return [
						'id' => $card->getId(),
						'uri' => $card->getId() . '.ics',
						'lastmodified' => $card->getLastModified(),
						'etag' => '"' . md5($card->getLastModified()) . '"',
						'calendarid' => $calendarId,
						'component' => 'vtodo',
						'size' => (int)strlen($vcalendar->serialize()),
						'calendardata' => $vcalendar->serialize()
					];
				}
			}
		}

		return null;
	}

	/**
	 * Creates a new calendar for a principal.
	 *
	 * If the creation was a success, an id must be returned that can be used to
	 * reference this calendar in other methods, such as updateCalendar.
	 *
	 * The id can be any type, including ints, strings, objects or array.
	 *
	 * @param string $principalUri
	 * @param string $calendarUri
	 * @param array $properties
	 * @return mixed
	 * @throws NotImplemented
	 */
	public function createCalendar($principalUri, $calendarUri, array $properties) {
		throw new NotImplemented();
	}

	/**
	 * Delete a calendar and all its objects
	 *
	 * @param mixed $calendarId
	 * @return void
	 * @throws NotImplemented
	 */
	public function deleteCalendar($calendarId) {
		throw new NotImplemented();
	}

	public function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch) {
		throw new NotImplemented();
	}

	/**
	 * Creates a new calendar object.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * It is possible to return an etag from this function, which will be used
	 * in the response to this PUT request. Note that the ETag must be
	 * surrounded by double-quotes.
	 *
	 * However, you should only really return this ETag if you don't mangle the
	 * calendar-data. If the result of a subsequent GET to this object is not
	 * the exact same as this request body, you should omit the ETag.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param string $calendarData
	 * @return string|null
	 */
	public function createCalendarObject($calendarId, $objectUri, $calendarData) {
		throw new NotImplemented();
		// TODO: Implement createCalendarObject() method.
	}

	/**
	 * Updates an existing calendarobject, based on it's uri.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * It is possible return an etag from this function, which will be used in
	 * the response to this PUT request. Note that the ETag must be surrounded
	 * by double-quotes.
	 *
	 * However, you should only really return this ETag if you don't mangle the
	 * calendar-data. If the result of a subsequent GET to this object is not
	 * the exact same as this request body, you should omit the ETag.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param string $calendarData
	 * @return string|null
	 */
	public function updateCalendarObject($calendarId, $objectUri, $calendarData) {
		throw new NotImplemented();
		// TODO: Implement updateCalendarObject() method.
	}

	/**
	 * Deletes an existing calendar object.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @return void
	 */
	public function deleteCalendarObject($calendarId, $objectUri) {
		throw new NotImplemented();
		// TODO: Implement deleteCalendarObject() method.
	}

	/**
	 * The getChanges method returns all the changes that have happened, since
	 * the specified syncToken in the specified calendar.
	 *
	 * This function should return an array, such as the following:
	 *
	 * [
	 *   'syncToken' => 'The current synctoken',
	 *   'added'   => [
	 *      'new.txt',
	 *   ],
	 *   'modified'   => [
	 *      'modified.txt',
	 *   ],
	 *   'deleted' => [
	 *      'foo.php.bak',
	 *      'old.txt'
	 *   ]
	 * );
	 *
	 * The returned syncToken property should reflect the *current* syncToken
	 * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
	 * property This is * needed here too, to ensure the operation is atomic.
	 *
	 * If the $syncToken argument is specified as null, this is an initial
	 * sync, and all members should be reported.
	 *
	 * The modified property is an array of nodenames that have changed since
	 * the last token.
	 *
	 * The deleted property is an array with nodenames, that have been deleted
	 * from collection.
	 *
	 * The $syncLevel argument is basically the 'depth' of the report. If it's
	 * 1, you only have to report changes that happened only directly in
	 * immediate descendants. If it's 2, it should also include changes from
	 * the nodes below the child collections. (grandchildren)
	 *
	 * The $limit argument allows a client to specify how many results should
	 * be returned at most. If the limit is not specified, it should be treated
	 * as infinite.
	 *
	 * If the limit (infinite or not) is higher than you're willing to return,
	 * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
	 *
	 * If the syncToken is expired (due to data cleanup) or unknown, you must
	 * return null.
	 *
	 * The limit is 'suggestive'. You are free to ignore it.
	 *
	 * @param string $calendarId
	 * @param string $syncToken
	 * @param int $syncLevel
	 * @param int $limit
	 * @return array
	 */
	public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null) {
		throw new NotImplemented();
		// TODO: Implement getChangesForCalendar() method.
	}

}