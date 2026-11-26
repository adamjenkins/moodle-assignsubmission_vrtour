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
$string['backtograding'] = 'Back to grading';
$string['backtosubmission'] = 'Back to my submission';
$string['captionlinks'] = 'Links lead to: {$a}';
$string['captionnopoints'] = 'No points of interest on this photo';
$string['captiononepoint'] = '1 point of interest to find here';
$string['captionphoto'] = 'Photo {$a->number} of {$a->total}: {$a->name}';
$string['captionpoints'] = '{$a} points of interest to find here';
$string['checkdone'] = 'Done';
$string['checklistheading'] = 'To hand it in, your tour needs:';
$string['checknophoto'] = 'Not ready to hand in: the tour has no 360° photo yet.';
$string['checkok'] = 'Ready to hand in: {$a->points} points of interest ({$a->min} needed).';
$string['checkshort'] = 'Not ready to hand in: {$a->points} of the {$a->min} points of interest needed.';
$string['checktodo'] = 'Not yet';
$string['configmaxbytes'] = 'The default largest image, audio or video file a student may add to a point of interest. Teachers can choose a smaller size for each assignment. The size of 360° photos is set in the VR room settings.';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['editorintro'] = 'A virtual tour is a set of 360° photos that visitors look around in, with points of interest that explain what they see.';
$string['editorrequirements'] = 'This assignment asks for at least {$a->minpoints} points of interest, on up to {$a->maxphotos} photos.';
$string['editorrequirementsnomin'] = 'Your tour can have up to {$a} photos.';
$string['editorstep1'] = 'Add a 360° photo for each place in your tour.';
$string['editorstep2'] = 'On each photo, press <strong>+ Point of interest</strong> to place points of interest, and <strong>+ Link to another photo</strong> so visitors can move from one place to the next.';
$string['editorstep3'] = 'Press <strong>Save</strong> often. When you have finished, press <strong>Back to my submission</strong> to hand your tour in.';
$string['edittour'] = 'Edit the tour';
$string['edittourcontinue'] = 'Continue your tour';
$string['edittournote'] = 'The tour is saved on its own, in the tour editor. Save what you have written in the other parts of this form before you open it.';
$string['edittourstart'] = 'Build your tour';
$string['emptystatetext'] = 'Each photo is one place in your tour. Visitors stand in the middle of it and look around.';
$string['emptystatetitle'] = 'Start your tour with a 360° photo';
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
$string['errornotphoto'] = '"{$a}" is not a 360° photo. Every place in a tour must be a 360° photo.';
$string['errornotprops'] = 'The photo "{$a}" has blocks in it. A tour cannot have blocks: delete them.';
$string['errornotzones'] = 'The photo "{$a}" has zones in it. A tour cannot have zones: delete them.';
$string['errornoview'] = 'You cannot see this tour.';
$string['errorpanorama'] = 'The photo "{$a->room}" cannot be used. {$a->problem}';
$string['errortoolarge'] = 'The tour is too large to be saved.';
$string['errortoomanyphotos'] = 'A tour in this assignment can have at most {$a} photos.';
$string['errortoomanyuploads'] = 'You have uploaded too many files. Save your tour (files you no longer use are then deleted) and try again.';
$string['errorupload'] = 'Choose one file to upload.';
$string['guidesummary'] = 'How to build your tour';
$string['kind_hotspot'] = 'Point of interest';
$string['kind_image'] = 'Image';
$string['kind_media'] = 'Audio or video';
$string['kind_text'] = 'Text board';
$string['label_editoraddhotspot'] = 'Point of interest';
$string['label_editoraddphotoscene'] = 'Add a photo';
$string['label_editoraddportal'] = 'Link to another photo';
$string['label_editordeletescene'] = 'Delete photo';
$string['label_editorenvironment'] = 'This photo';
$string['label_editorenvironmentfile'] = 'Photo file';
$string['label_editormovescenedown'] = 'Move photo down';
$string['label_editormovesceneup'] = 'Move photo up';
$string['label_editornorthoffset'] = 'Which way is forward (degrees)';
$string['label_editorrenamescene'] = 'Photo name';
$string['label_editorscenes'] = 'Photos';
$string['label_editorsky'] = 'Colour above and below a wide photo';
$string['label_editorstartscene'] = 'Visitors start here';
$string['label_editortargetscene'] = 'Leads to photo';
$string['label_editoruploadtitle'] = 'Add a 360° photo';
$string['maxbytes'] = 'Largest image, audio or video file';
$string['maxbytes_help'] = 'The largest image, audio or video file a student may add to a point of interest. 360° photos have their own limit, set for the whole site and shown below.';
$string['maxphotos'] = 'Most photos in a tour';
$string['maxphotos_help'] = 'The largest number of 360° photos a student\'s tour may have.';
$string['minpoints'] = 'Points of interest needed';
$string['minpoints_help'] = 'A student cannot hand in a tour with fewer points of interest than this. Every object a student places on a photo counts, except the links that lead to another photo. Choose 0 for no minimum.';
$string['needaphoto'] = 'a 360° photo';
$string['needmorepoints'] = '{$a} more points of interest';
$string['needonepoint'] = '1 more point of interest';
$string['needphoto'] = 'At least one 360° photo';
$string['needpoints'] = 'At least {$a} points of interest';
$string['nocamera'] = 'No 360° camera?';
$string['nocameraflat'] = 'An ordinary photo also works, but it is stretched all the way round the visitor and looks distorted. Use it only when you have nothing better.';
$string['nocamerasphere'] = 'For a full sphere (floor and ceiling too), use a 360° camera or a phone app that makes photo spheres. Save the photo as a JPEG.';
$string['nocamerastep1'] = 'Open your phone\'s camera and switch to <strong>Panorama</strong> (on some phones it is under <strong>More</strong>).';
$string['nocamerastep2'] = 'Stand in one spot, hold the phone upright and turn slowly in a circle, as far round as it lets you.';
$string['nocamerastep3'] = 'Upload the photo here. It is shown as a band all around the visitor.';
$string['notouryet'] = 'No tour yet.';
$string['openingtour'] = 'Opening the tour…';
$string['opentour'] = 'Open the tour';
$string['outlineheading'] = 'Tour outline';
$string['outlinelink'] = 'Link to {$a}';
$string['outlinenopoints'] = 'No points of interest on this photo.';
$string['outlinephoto'] = 'Photo {$a->number}: {$a->name}';
$string['outlinestart'] = 'Visitors start here.';
$string['outlineunreachable'] = 'No link leads to this photo, so visitors cannot reach it.';
$string['owneronly'] = 'Only the student whose submission this is can edit the tour.';
$string['photolimits'] = 'Students can upload 360° photos up to {$a->maxwidth} pixels wide and {$a->maxsize}.';
$string['photolimitsheading'] = '360° photo limits';
$string['photolimitslink'] = 'Change these limits';
$string['photolimitssite'] = 'The largest 360° photo students may upload, and the smaller copies made for computers and phones, are set in the <a href="{$a}">VR room settings</a>. They apply to teachers\' photo rooms too.';
$string['pluginname'] = 'Virtual tour';
$string['practice_ballroom'] = 'Old ballroom';
$string['practice_museum'] = 'Museum hall';
$string['practice_musichall'] = 'Music hall';
$string['practicecredit'] = 'Practice photos from Poly Haven (CC0). Replace them with your own photos before you hand in your tour.';
$string['practiceheading'] = 'Or try the editor with a practice photo:';
$string['practiceroomname'] = 'Practice: {$a}';
$string['privacy:metadata:assignmentid'] = 'The assignment the tour belongs to.';
$string['privacy:metadata:filepurpose'] = 'The 360° photos and the images, audio and video of the tour.';
$string['privacy:metadata:numphotos'] = 'The number of photos in the tour.';
$string['privacy:metadata:numpoints'] = 'The number of points of interest in the tour.';
$string['privacy:metadata:scenesjson'] = 'The tour: its photos, the links between them and the points of interest with their names and texts.';
$string['privacy:metadata:submissionpurpose'] = 'The submission the tour belongs to.';
$string['privacy:metadata:tablepurpose'] = 'The virtual tour of each submission.';
$string['privacy:metadata:timemodified'] = 'When the tour was last saved.';
$string['requirementphotos'] = 'Photos';
$string['requirementpoints'] = 'Points of interest';
$string['sofar'] = '{$a} so far';
$string['startbutton'] = 'Add your first 360° photo';
$string['summary'] = 'Photos: {$a->photos}. Points of interest: {$a->points}.';
$string['tipfirstpoint'] = 'Next: drag to look around your photo, then press <strong>+ Point of interest</strong>. Click the photo to move the point.';
$string['tiplabel'] = 'What to do next';
$string['tipmorepoint'] = 'Next: add 1 more point of interest, on this photo or on another photo linked to it.';
$string['tipmorepoints'] = 'Next: add {$a} more points of interest, on this photo or on another photo linked to it.';
$string['tipready'] = 'Your tour has everything it needs. Press <strong>Save</strong>, then <strong>Back to my submission</strong> to hand it in.';
$string['tourdonedraftneeds'] = 'Your tour is saved as a draft. Still needed before you can hand it in: {$a}.';
$string['tourdonedraftready'] = 'Your tour is saved as a draft and has everything it needs. Press <strong>Submit assignment</strong> to hand it in.';
$string['tourdonehandin'] = 'Your tour is saved. To hand it in, press Save changes.';
$string['tourdonesaved'] = 'Your tour is saved.';
$string['tourwhat'] = 'A virtual tour is a few 360° photos of a place, linked together, with points of interest that tell visitors what they are looking at.';
$string['uploadfile'] = 'File';
$string['uploadnocamera'] = 'No 360° camera? A phone\'s Panorama mode works too, and so does an ordinary photo, which looks stretched.';
$string['uploadpanoramahelp'] = 'Choose a 360° photo: a JPEG, PNG or WebP photo up to {$a->maxwidth} pixels wide and {$a->maxsize}. For best results, use an equirectangular photo twice as wide as it is high, such as a phone camera\'s photo sphere; a wider photo is shown as a band around the horizon.';
$string['verdictcounts'] = 'Photos: {$a->photos} · Points of interest: {$a->points} ({$a->min} needed)';
$string['verdictcountsnomin'] = 'Photos: {$a->photos} · Points of interest: {$a->points}';
$string['verdictunreachable'] = 'Photos no link leads to: {$a}';
