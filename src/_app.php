<?php
/**
 * App initialisation.
 *
 * @package Slackemon
 */

define( 'SLACKEMON_VERSION', '0.0.41' );

require_once( __DIR__ . '/../lib/functions.php' );

require_once( __DIR__ . '/apis.php'       );
require_once( __DIR__ . '/battles.php'    );
require_once( __DIR__ . '/catching.php'   );
require_once( __DIR__ . '/data.php'       );
require_once( __DIR__ . '/evolution.php'  );
require_once( __DIR__ . '/items.php'      );
require_once( __DIR__ . '/moves.php'      );
require_once( __DIR__ . '/options.php'    );
require_once( __DIR__ . '/organising.php' );
require_once( __DIR__ . '/players.php'    );
require_once( __DIR__ . '/spawns.php'     );
require_once( __DIR__ . '/stats.php'      );
require_once( __DIR__ . '/templating.php' );
require_once( __DIR__ . '/trading.php'    );
require_once( __DIR__ . '/travel.php'     );
require_once( __DIR__ . '/weather.php'    );

require_once( __DIR__ . '/menus/achievements-menu.php' );
require_once( __DIR__ . '/menus/battle-menu.php'       );
require_once( __DIR__ . '/menus/items-menu.php'        );
require_once( __DIR__ . '/menus/main-menu.php'         );
require_once( __DIR__ . '/menus/onboarding-menu.php'   );
require_once( __DIR__ . '/menus/pokemon-menu.php'      );
require_once( __DIR__ . '/menus/tools-menu.php'        );
require_once( __DIR__ . '/menus/travel-menu.php'       );

// The end!
