<?php
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
 * English strings for the virtual tour submission plugin.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allowaudio'] = 'Allow audio on points of interest';
$string['allowimage'] = 'Allow images on points of interest';
$string['allowimage_help'] = 'Students can always add text to a point of interest. These settings say whether they may also add images, audio and video.';
$string['allowvideo'] = 'Allow video on points of interest';
$string['backtoassignment'] = 'Back to the assignment';
$string['backtosubmission'] = 'Back to my submission';
$string['configmaxbytes'] = 'The default largest image, audio or video file a student may add to a point of interest. Teachers can choose a smaller size for each assignment. The size of 360° photos is set in the VR room settings.';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['editorintro'] = 'Upload your 360° photos and add points of interest to them. Your tour is kept each time you press Save in the editor. When you are finished, go back to your submission and save it (or submit it) there.';
$string['edittour'] = 'Edit the tour';
$string['edittournote'] = 'The tour opens in its own editor. Save any other changes on this page first.';
$string['enabled'] = 'Virtual tour';
$string['enabled_help'] = 'If enabled, students make a virtual tour as their submission: they upload 360° photos, link them together and add points of interest with text, images, audio or video.';
$string['errorconflict'] = 'Your tour was changed somewhere else after you opened the editor. Reload the editor and make your changes again.';
$string['errorfile'] = 'The file "{$a}" is not one of the files of your tour. Upload it again.';
$string['errorfilekind'] = 'The file "{$a}" cannot be used here: it is not a kind of file this assignment allows.';
$string['errorfilesize'] = 'The file "{$a->name}" is too large. Files can be at most {$a->max}.';
$string['errorinvalid'] = 'The tour cannot be saved: {$a}';
$string['errorkind'] = '"{$a->name}" in the photo "{$a->room}" is a kind of object this assignment does not allow. Delete it.';
$string['errorminpoints'] = 'Points of interest needed before you can submit the tour: {$a->min}. It has {$a->count}.';
$string['errornoedit'] = 'You cannot change this tour now.';
$string['errornophoto'] = 'Your tour needs at least one 360° photo before you can submit it.';
$string['errornotenabled'] = 'This assignment does not take virtual tours.';
$string['errornotphoto'] = 'The room "{$a}" is not a 360° photo. Every room of a tour must be a 360° photo.';
$string['errornotprops'] = 'The photo "{$a}" has blocks in it. A tour cannot have blocks: delete them.';
$string['errornotzones'] = 'The photo "{$a}" has zones in it. A tour cannot have zones: delete them.';
$string['errornoview'] = 'You cannot see this tour.';
$string['errorpanorama'] = 'The 360° photo of "{$a->room}" cannot be used. {$a->problem}';
$string['errortoolarge'] = 'The tour is too large to be saved.';
$string['errortoomanyphotos'] = 'A tour in this assignment can have at most {$a} photos.';
$string['errortoomanyuploads'] = 'You have uploaded too many files. Save your tour (files you no longer use are then deleted) and try again.';
$string['errorupload'] = 'Choose one file to upload.';
$string['maxbytes'] = 'Largest media file';
$string['maxbytes_help'] = 'The largest image, audio or video file a student may add to a point of interest. The size of 360° photos is set for the whole site.';
$string['maxphotos'] = 'Most photos';
$string['maxphotos_help'] = 'The largest number of 360° photos a student\'s tour may have.';
$string['minpoints'] = 'Fewest points of interest';
$string['minpoints_help'] = 'A student cannot submit a tour with fewer points of interest than this. Every object a student places on a photo counts, except the links that lead to another photo.';
$string['minpointsnote'] = 'Points of interest needed: {$a}.';
$string['notouryet'] = 'No tour yet.';
$string['opentour'] = 'Open the tour';
$string['owneronly'] = 'Only the student whose submission this is can edit the tour.';
$string['photolimits'] = 'Students can upload 360° photos up to {$a->maxwidth} pixels wide and {$a->maxsize}.';
$string['photolimitsheading'] = '360° photo limits';
$string['photolimitslink'] = 'Change these limits';
$string['photolimitssite'] = 'The largest 360° photo students may upload, and the smaller copies made for computers and phones, are set in the <a href="{$a}">VR room settings</a>. They apply to teachers\' photo rooms too.';
$string['pluginname'] = 'Virtual tour';
$string['privacy:metadata:assignmentid'] = 'The assignment the tour belongs to.';
$string['privacy:metadata:filepurpose'] = 'The 360° photos and the images, audio and video of the tour.';
$string['privacy:metadata:numphotos'] = 'The number of photos in the tour.';
$string['privacy:metadata:numpoints'] = 'The number of points of interest in the tour.';
$string['privacy:metadata:scenesjson'] = 'The tour: its photos, the links between them and the points of interest with their names and texts.';
$string['privacy:metadata:submissionpurpose'] = 'The submission the tour belongs to.';
$string['privacy:metadata:tablepurpose'] = 'The virtual tour of each submission.';
$string['privacy:metadata:timemodified'] = 'When the tour was last saved.';
$string['summary'] = 'Photos: {$a->photos}. Points of interest: {$a->points}.';
$string['uploadfile'] = 'File';
$string['uploadpanoramahelp'] = 'Choose a 360° photo: a JPEG, PNG or WebP photo up to {$a->maxwidth} pixels wide and {$a->maxsize}. For best results, use an equirectangular photo twice as wide as it is high, such as a phone camera\'s photo sphere; a wider photo is shown as a band around the horizon.';
