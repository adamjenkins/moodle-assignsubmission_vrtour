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
 * reads it and starts vrroomruntime_builtin/standalone, with a caption over the viewer saying which
 * photo is showing and what is in it.
 *
 * @module     assignsubmission_vrtour/viewer
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {init as initStandalone} from 'vrroomruntime_builtin/standalone';

/** @var {number} How often to ask the viewer which photo is showing, for viewers that send no event. */
const POLL_MS = 600;

/** @var {number} How long the "Opening the tour" panel may stay before it goes anyway. */
const LOADING_MS = 20000;

/**
 * Keep the caption (which photo this is and what is in it) and the loading panel in step with the viewer.
 *
 * The caption sits inside the viewer's viewport, so it stays in view in fullscreen. The viewer says
 * which photo it shows through a 'vrroom:scenechange' event; with an older viewer that sends none,
 * the caption asks root.vrroomTest.currentScene() now and then.
 *
 * @param {HTMLElement} root The viewer root.
 * @param {HTMLElement} stage The element around the root, holding the caption and the loading panel.
 * @param {Object} captions {sceneid: {title, cue}}
 */
const followScenes = (root, stage, captions) => {
    const caption = stage.querySelector('[data-region="assignsubmission_vrtour-caption"]');
    const loading = stage.querySelector('[data-region="assignsubmission_vrtour-loading"]');
    let shown = null;
    let timer = null;

    const hideLoading = () => {
        if (loading) {
            loading.classList.add('is-gone');
        }
    };
    const show = (sceneid) => {
        if (!caption || !captions[sceneid]) {
            return;
        }
        const viewport = root.querySelector('[data-region="vrroom-viewport"]');
        if (viewport && caption.parentElement !== viewport) {
            viewport.appendChild(caption);
        }
        if (sceneid === shown && !caption.hidden) {
            return;
        }
        shown = sceneid;
        caption.querySelector('[data-region="assignsubmission_vrtour-captiontitle"]').textContent = captions[sceneid].title;
        caption.querySelector('[data-region="assignsubmission_vrtour-captioncue"]').textContent = captions[sceneid].cue;
        caption.hidden = false;
        // Restart the short fade-in, so a new photo's caption is noticed.
        caption.classList.remove('is-new');
        void caption.offsetWidth;
        caption.classList.add('is-new');
    };
    const check = () => {
        const ready = root.dataset.vrroomReady;
        if (ready) {
            hideLoading();
        }
        if (ready !== '3d') {
            if (caption) {
                caption.hidden = true;
                shown = null;
            }
            return;
        }
        const hooks = root.vrroomTest;
        if (hooks && typeof hooks.currentScene === 'function') {
            show(hooks.currentScene());
        }
    };

    // VR room sends 'vrroom:scenechange' from 2026-11-26 (8e97ee0) on; the first one ends the poll.
    root.addEventListener('vrroom:scenechange', (e) => {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
        if (e.detail && e.detail.sceneid && root.dataset.vrroomReady === '3d') {
            show(e.detail.sceneid);
        }
    });
    new MutationObserver(check).observe(root, {attributes: true, attributeFilter: ['data-vrroom-ready']});
    timer = window.setInterval(() => {
        if (!document.body.contains(root)) {
            window.clearInterval(timer);
            timer = null;
            return;
        }
        check();
    }, POLL_MS);
    window.setTimeout(hideLoading, LOADING_MS);
};

/**
 * Start the viewer.
 *
 * @param {Object} options
 * @param {string} options.rootid Id of the viewer's root element.
 * @param {string} options.configid Id of the script element holding the config JSON.
 * @param {Object} [options.captions] {sceneid: {title, cue}} for the caption over the viewer.
 * @returns {Promise|undefined}
 */
export const init = ({rootid, configid, captions = {}}) => {
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
    const stage = root.closest('[data-region="assignsubmission_vrtour-stage"]');
    if (stage) {
        followScenes(root, stage, captions || {});
    }
    return Promise.resolve(initStandalone({rootid: rootid, config: config})).catch(Notification.exception);
};
