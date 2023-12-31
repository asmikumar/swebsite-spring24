<?php

/*
 * Created on July 30, 2007
 *
 * API for MediaWiki 1.8+
 *
 * Copyright (C) 2007 Roan Kattouw <Firstname>.<Lastname>@home.nl
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	// Eclipse helper - will be ignored in production
	require_once ( 'ApiQueryBase.php' );
}

/**
 * Query module to get information about a list of users
 *
 * @ingroup API
 */
 class ApiQueryUsers extends ApiQueryBase {

	public function __construct( $query, $moduleName ) {
		parent :: __construct( $query, $moduleName, 'us' );
	}
	
	/**
	 * Get an array mapping token names to their handler functions.
	 * The prototype for a token function is func($user)
	 * it should return a token or false (permission denied)
	 * @return array(tokenname => function)
	 */
	protected function getTokenFunctions() {
		// Don't call the hooks twice
		if ( isset( $this->tokenFunctions ) )
			return $this->tokenFunctions;

		// If we're in JSON callback mode, no tokens can be obtained
		if ( !is_null( $this->getMain()->getRequest()->getVal( 'callback' ) ) )
			return array();

		$this->tokenFunctions = array(
			'userrights' => array( 'ApiQueryUsers', 'getUserrightsToken' ),
		);
		wfRunHooks( 'APIQueryUsersTokens', array( &$this->tokenFunctions ) );
		return $this->tokenFunctions;
	}
	
	public static function getUserrightsToken( $user )
	{
		global $wgUser;
		// Since the permissions check for userrights is non-trivial,
		// don't bother with it here
		return $wgUser->editToken( $user->getName() );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		$r = array();

		if ( !is_null( $params['prop'] ) ) {
			$this->prop = array_flip( $params['prop'] );
		} else {
			$this->prop = array();
		}

		$users = (array)$params['users'];
		$goodNames = $done = array();
		$result = $this->getResult();
		// Canonicalize user names
		foreach ( $users as $u ) {
			$n = User::getCanonicalName( $u );
			if ( $n === false || $n === '' )
			{
				$vals = array( 'name' => $u, 'invalid' => '' );
				$fit = $result->addValue( array( 'query', $this->getModuleName() ),
						null, $vals );
				if ( !$fit )
				{
					$this->setContinueEnumParameter( 'users',
							implode( '|', array_diff( $users, $done ) ) );
					$goodNames = array();
					break;
				}
				$done[] = $u;
			}
			 else
				$goodNames[] = $n;
		}

		if ( count( $goodNames ) )
		{
			$db = $this->getDb();
			$this->addTables( 'user', 'u1' );
			$this->addFields( 'u1.*' );
			$this->addWhereFld( 'u1.user_name', $goodNames );

			if ( isset( $this->prop['groups'] ) ) {
				$this->addTables( 'user_groups' );
				$this->addJoinConds( array( 'user_groups' => array( 'LEFT JOIN', 'ug_user=u1.user_id' ) ) );
				$this->addFields( 'ug_group' );
			}
			if ( isset( $this->prop['blockinfo'] ) ) {
				$this->addTables( 'ipblocks' );
				$this->addTables( 'user', 'u2' );
				$u2 = $this->getAliasedName( 'user', 'u2' );
				$this->addJoinConds( array(
					'ipblocks' => array( 'LEFT JOIN', 'ipb_user=u1.user_id' ),
					$u2 => array( 'LEFT JOIN', 'ipb_by=u2.user_id' ) ) );
				$this->addFields( array( 'ipb_reason', 'u2.user_name AS blocker_name' ) );
			}

			$data = array();
			$res = $this->select( __METHOD__ );
			while ( ( $r = $db->fetchObject( $res ) ) ) {
				$user = User::newFromRow( $r );
				$name = $user->getName();
				$data[$name]['name'] = $name;
				if ( isset( $this->prop['editcount'] ) )
					$data[$name]['editcount'] = intval( $user->getEditCount() );
				if ( isset( $this->prop['registration'] ) )
					$data[$name]['registration'] = wfTimestampOrNull( TS_ISO_8601, $user->getRegistration() );
				if ( isset( $this->prop['groups'] ) && !is_null( $r->ug_group ) )
					// This row contains only one group, others will be added from other rows
					$data[$name]['groups'][] = $r->ug_group;
				if ( isset( $this->prop['blockinfo'] ) && !is_null( $r->blocker_name ) ) {
					$data[$name]['blockedby'] = $r->blocker_name;
					$data[$name]['blockreason'] = $r->ipb_reason;
				}
				if ( isset( $this->prop['emailable'] ) && $user->canReceiveEmail() )
					$data[$name]['emailable'] = '';

				if ( isset( $this->prop['gender'] ) ) {
					$gender = $user->getOption( 'gender' );
					if ( strval( $gender ) === '' ) {
						$gender = 'unknown';
					}
					$data[$name]['gender'] = $gender;
				}

				if ( !is_null( $params['token'] ) )
				{
					$tokenFunctions = $this->getTokenFunctions();
					foreach ( $params['token'] as $t )
					{
						$val = call_user_func( $tokenFunctions[$t], $user );
						if ( $val === false )
							$this->setWarning( "Action '$t' is not allowed for the current user" );
						else
							$data[$name][$t . 'token'] = $val;
					}
				}
			}
		}
		// Second pass: add result data to $retval
		foreach ( $goodNames as $u ) {
			if ( !isset( $data[$u] ) ) {
				$data[$u] = array( 'name' => $u );
				$urPage = new UserrightsPage;
				$iwUser = $urPage->fetchUser( $u );
				if ( $iwUser instanceof UserRightsProxy ) {
					$data[$u]['interwiki'] = '';
					if ( !is_null( $params['token'] ) )
					{
						$tokenFunctions = $this->getTokenFunctions();
						foreach ( $params['token'] as $t )
						{
							$val = call_user_func( $tokenFunctions[$t], $iwUser );
							if ( $val === false )
								$this->setWarning( "Action '$t' is not allowed for the current user" );
							else
								$data[$u][$t . 'token'] = $val;
						}
					}
				} else
					$data[$u]['missing'] = '';
			} else {
				if ( isset( $this->prop['groups'] ) && isset( $data[$u]['groups'] ) )
					$this->getResult()->setIndexedTagName( $data[$u]['groups'], 'g' );
			}
			$fit = $result->addValue( array( 'query', $this->getModuleName() ),
					null, $data[$u] );
			if ( !$fit )
			{
				$this->setContinueEnumParameter( 'users',
						implode( '|', array_diff( $users, $done ) ) );
				break;
			}
			$done[] = $u;
		}
		return $this->getResult()->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'user' );
	}

	public function getCacheMode( $params ) {
		if ( isset( $params['token'] ) ) {
			return 'private';
		} else {
			return 'public';
		}
	}

	public function getAllowedParams() {
		return array (
			'prop' => array (
				ApiBase :: PARAM_DFLT => null,
				ApiBase :: PARAM_ISMULTI => true,
				ApiBase :: PARAM_TYPE => array (
					'blockinfo',
					'groups',
					'editcount',
					'registration',
					'emailable',
					'gender',
				)
			),
			'users' => array(
				ApiBase :: PARAM_ISMULTI => true
			),
			'token' => array(
				ApiBase :: PARAM_TYPE => array_keys( $this->getTokenFunctions() ),
				ApiBase :: PARAM_ISMULTI => true
			),
		);
	}

	public function getParamDescription() {
		return array (
			'prop' => array(
				'What pieces of information to include',
				'  blockinfo    - tags if the user is blocked, by whom, and for what reason',
				'  groups       - lists all the groups the user belongs to',
				'  editcount    - adds the user\'s edit count',
				'  registration - adds the user\'s registration timestamp',
				'  emailable    - tags if the user can and wants to receive e-mail through [[Special:Emailuser]]',
				'  gender       - tags the gender of the user. Returns "male", "female", or "unknown"',
			),
			'users' => 'A list of users to obtain the same information for',
			'token' => 'Which tokens to obtain for each user',
		);
	}

	public function getDescription() {
		return 'Get information about a list of users';
	}

	protected function getExamples() {
		return 'api.php?action=query&list=users&ususers=brion|TimStarling&usprop=groups|editcount|gender';
	}

	public function getVersion() {
		return __CLASS__ . ': $Id: ApiQueryUsers.php 69932 2010-07-26 08:03:21Z tstarling $';
	}
}
