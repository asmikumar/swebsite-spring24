<?php

/**
 * Maintenance script to re-initialise or update the site statistics table
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
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @ingroup Maintenance
 * @author Brion Vibber
 * @author Rob Church <robchur@gmail.com>
 * @licence GNU General Public Licence 2.0 or later
 */

require_once( dirname(__FILE__) . '/Maintenance.php' );

class InitStats extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Re-initialise the site statistics tables";
		$this->addOption( 'update', 'Update the existing statistics (preserves the ss_total_views field)' );
		$this->addOption( 'noviews', "Don't update the page view counter" );
		$this->addOption( 'active', 'Also update active users count' );
		$this->addOption( 'use-master', 'Count using the master database' );
	}

	public function execute() {
		$this->output( "Refresh Site Statistics\n\n" );
		$counter = new SiteStatsInit( $this->hasOption( 'use-master' ) );

		$this->output( "Counting total edits..." );
		$edits = $counter->edits();
		$this->output( "{$edits}\nCounting number of articles..." );

		$good  = $counter->articles();
		$this->output( "{$good}\nCounting total pages..." );

		$pages = $counter->pages();
		$this->output( "{$pages}\nCounting number of users..." );

		$users = $counter->users();
		$this->output( "{$users}\nCounting number of images..." );

		$image = $counter->files();
		$this->output( "{$image}\n" );

		if( !$this->hasOption('noviews') ) {
			$this->output( "Counting total page views..." );
			$views = $counter->views();
			$this->output( "{$views}\n" );
		}

		if( $this->hasOption( 'active' ) ) {
			$this->output( "Counting active users..." );
			$active = SiteStatsUpdate::cacheUpdate();
			$this->output( "{$active}\n" );
		}

		$this->output( "\nUpdating site statistics..." );

		if( $this->hasOption( 'update' ) ) {
			$counter->update();
		} else {
			$counter->refresh();
		}

		$this->output( "done.\n" );
	}
}

$maintClass = "InitStats";
require_once( DO_MAINTENANCE );
