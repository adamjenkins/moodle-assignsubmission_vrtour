// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Show a submission's virtual tour in the built-in viewer.
 *
 * The page embeds the viewer config as JSON (it is too large for js_call_amd's arguments); this
 * reads it and starts vrroomruntime_builtin/standalone.
 *
 * @module     assignsubmission_vrtour/viewer
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {init as initStandalone} from 'vrroomruntime_builtin/standalone';

/**
 * Start the viewer.
 *
 * @param {Object} options
 * @param {string} options.rootid Id of the viewer's root element.
 * @param {string} options.configid Id of the script element holding the config JSON.
 * @returns {Promise|undefined}
 */
export const init = ({rootid, configid}) => {
    const root = document.getElementById(rootid);
    const holder = document.getElementById(configid);
    if (!root || !holder) {
        return undefined;
    }
    let config = null;
    try {
        config = JSON.parse(holder.textContent || 'null');
    } catch (error) {
        Notification.exception(error);
        return undefined;
    }
    if (!config || typeof config !== 'object') {
        return undefined;
    }
    return Promise.resolve(initStandalone({rootid: rootid, config: config})).catch(Notification.exception);
};
