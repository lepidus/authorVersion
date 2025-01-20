<?php

/**
 * @defgroup plugins_generic_authorVersion
 */
/**
 * @file plugins/generic/authorVersion/index.php
 *
 * Copyright (c) 2020-2023 Lepidus Tecnologia
 * Copyright (c) 2020-2023 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @ingroup plugins_generic_authorVersion
 * @brief Wrapper for the Author Version plugin.
 *
 */
require_once('AuthorVersionPlugin.inc.php');
return new AuthorVersionPlugin();
