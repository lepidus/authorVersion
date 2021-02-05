<?php
/**
 * @defgroup plugins_generic_authorVersion
 */
/**
 * @file plugins/generic/authorVersion/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2029 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_authorVersion
 * @brief Wrapper for the Author Version plugin.
 *
 */
require_once('AuthorVersionPlugin.inc.php');
return new AuthorVersionPlugin();