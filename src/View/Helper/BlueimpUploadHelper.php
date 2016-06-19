<?php
namespace CakephpBlueimpUpload\View\Helper;

use Cake\View\Helper;
use Cake\View\View;

/**
 * BlueimpUpload helper
 *
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Cake\View\Helper\FormHelper $Form
 */
class BlueimpUploadHelper extends Helper
{
    public $helpers = ['Html', 'Form'];

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
		'block' => true
	];

    public function chunked($upload_id, $options = []) {
		$defaults = [
            'upload_url'                     => null,
            'input_file_text'                => __d('cakephp_blueimp_upload', 'Upload'),
            'success_message'                => __d('cakephp_blueimp_upload', 'Upload successful'),
            'max_chunk_size'                 => 2 * 1024 * 1024,
            'multiple_select'                => true,
            'csrf_token'                     => null,
            'default_js'                     => true,
            'default_css'                    => true,
            'display_progress'               => true,
            'hide_progressbar_after_upload'  => true,

            'add'                            => null,
            'done'                           => null,
            'fail'                           => null,
            'always'                         => null,
            'upload_success_callback'        => null,

            'template'                       => null,
            'input_button_selector'          => null,
            'progress_bar_zone_selector'     => null,
            'progress_bar_selector'          => null,
            'size_uploaded_selector'         => null,
            'notification_selector'          => null,
        ] + $this->config();

        $options += $defaults;

        $options['csrf_token']                 = isset($options['csrf_token'])                 ? $options['csrf_token']                 : $this->request->param('_csrfToken');
        $options['input_button_selector']      = isset($options['input_button_selector'])      ? $options['input_button_selector']      : '#' . $upload_id . ' input[type=file]';
        $options['progress_bar_zone_selector'] = isset($options['progress_bar_zone_selector']) ? $options['progress_bar_zone_selector'] : '#' . $upload_id . ' .progress-bar-zone';
        $options['progress_bar_selector']      = isset($options['progress_bar_selector'])      ? $options['progress_bar_selector']      : '#' . $upload_id . ' .progress';
        $options['size_uploaded_selector']     = isset($options['size_uploaded_selector'])     ? $options['size_uploaded_selector']     : '#' . $upload_id . ' .size';
        $options['notification_selector']      = isset($options['notification_selector'])      ? $options['notification_selector']      : '#' . $upload_id . '_notification';

        /*************************/

        if ($options['default_js']) {
            $this->Html->script('CakephpBlueimpUpload.blueimp-jquery-file-upload/js/vendor/jquery.ui.widget', ['block' => $options['block']]);
            $this->Html->script('CakephpBlueimpUpload.blueimp-jquery-file-upload/js/jquery.iframe-transport', ['block' => $options['block']]);
            $this->Html->script('CakephpBlueimpUpload.blueimp-jquery-file-upload/js/jquery.fileupload', ['block' => $options['block']]);
			$this->Html->script('CakephpBlueimpUpload.blueimp-jquery-file-upload/js/jquery.fileupload-process', ['block' => $options['block']]);
			$this->Html->script('CakephpBlueimpUpload.blueimp-jquery-file-upload/js/jquery.fileupload-image', ['block' => $options['block']]);
            $this->Html->script('CakephpBlueimpUpload.chunked', ['block' => $options['block']]);
        }

        if ($options['default_css']) {
            $this->Html->css('CakephpBlueimpUpload.default', ['block' => $options['block']]);
        }

        if (!isset($options['template'])) {
            $template   = [];
            $template[] = '<div id="{upload-id}">';
            $template[] = '';
            $template[] = '    <span class="btn btn-default fileinput-button">';
            $template[] = '        <span>{input_file_text}</span>';
            if($options['multiple_select'])
            {
                $template[] = '        <input type="file" name="files[]" data-url="{data-url}" multiple>';
            }
            else
            {
                $template[] = '        <input type="file" name="files[]" data-url="{data-url}">';
            }
            $template[] = '    </span>';
            $template[] = '';
            $template[] = '    <div class="progress-bar-zone" style="display:none;">';
            $template[] = '';
            $template[] = '        <div class="progress">';
            $template[] = '            <div class="progress-bar bar progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="min-width:2em;">';
            $template[] = '                <span class="percent">0%</span>';
            $template[] = '            </div>';
            $template[] = '        </div>';
            $template[] = '        <div style="margin:-15px 0 10px 0;">';
            $template[] = '            <span class="size"></span>';
            $template[] = '        </div>';
            $template[] = '    </div>';
            $template[] = '';
            $template[] = '    <div id="{notification-id}" style="display:none;padding:5px;">';
            $template[] = '    </div>';
            $template[] = '';
            $template[] = '</div>';

            $options['template'] = implode("\n", $template);
        }

        /*************************/

        $html = $options['template'];
        $html = str_ireplace('{upload-id}',       $upload_id,                   $html);
        $html = str_ireplace('{notification-id}', $upload_id . '_notification', $html);
        $html = str_ireplace('{input_file_text}', $options['input_file_text'],  $html);
        $html = str_ireplace('{data-url}',        $options['upload_url'],       $html);

        /*************************/

        $script   = [];
        $script[] = '$(document).ready(function(){';
        $script[] = '    ';
        $script[] = '    var options = {';
        $script[] = '        "input_button_selector" : "' . $options['input_button_selector'] .'",';
        $script[] = '        "maxChunkSize"          : ' . $options['max_chunk_size'] . ',';
        $script[] = '        "sequentialUploads"     : true,';
        $script[] = '        "dataType"              : "json",';
        $script[] = '        "progress_bar_selector" : "' . $options['progress_bar_selector'] . '",';
        $script[] = '        "display_progress"      : ' . ($options['display_progress'] ? 'true' : 'false');
        $script[] = '    }';
        $script[] = '    ';

        /**
         * 'add' callback
         */

        if(!isset($options['add']))
        {
            $script[] = '    options["add"] = function(e, data){';
            $script[] = '        ';
            $script[] = '        if(typeof(data.headers) == "undefined"){';
            $script[] = '            data.headers = {}';
            $script[] = '        }';
            $script[] = '        ';
            $script[] = '        data.headers["X-Upload-id"] = ChunkedFileUpload.generate_upload_id();';

            if(isset($options['csrf_token']))
            {
                $script[] = '        data.headers["X-CSRF-Token"] = "' . $options['csrf_token'] . '";';
            }

            $script[] = '        ';
            if($options['display_progress'])
            {
                $script[] = '        ChunkedFileUpload.resetProgressBar("' . $options['progress_bar_selector'] . '", "' . $options['size_uploaded_selector'] . '")';
                $script[] = '        ChunkedFileUpload.showProgressBar("' . $options['progress_bar_zone_selector'] . '");';
            }

            $script[] = '        ChunkedFileUpload.hideNotification("' . $options['notification_selector'] . '");';
            $script[] = '        ';
            $script[] = '        data.submit();';
            $script[] = '    };';
        }
        else
        {
            $script[] = '    options["add"] = ' . $options['add'];
        }

        /***
         * 'progressall' callback
         */

        $script[] = '';
        if(!isset($options['progressall']))
        {
            $script[] = '    options["progressall"] = function(e, data){';
            $script[] = '        ';

            if($options['display_progress'])
            {
                $script[] = '        ChunkedFileUpload.setBarData(data, "' . $options['progress_bar_selector'] . '", "' . $options['size_uploaded_selector'] . '");';
                $script[] = '        ';
            }

            $script[] = '        if(data.loaded == data.total)';
            $script[] = '        {';

                if($options['hide_progressbar_after_upload'])
                {
                    $script[] = '            $("' . $options['progress_bar_zone_selector'] . '").fadeOut(200, function(){';
                    $script[] = '                ChunkedFileUpload.showSuccessMessage("' . $options['notification_selector'] . '", "' . $options['progress_bar_zone_selector'] . '", "' . str_replace('"', '\"', $options['success_message']) . '");';
                    $script[] = '            });';
                }
                else
                {
                    $script[] = '            ChunkedFileUpload.showSuccessMessage("' . $options['notification_selector'] . '", "' . $options['progress_bar_zone_selector'] . '", "' . str_replace('"', '\"', $options['success_message']) . '");';
                }

            if(isset($options['upload_success_callback']))
            {
                $script[] = '            ';
                $script[] = '            ' . $options['upload_success_callback'] . '(e, data);';
            }

            $script[] = '        }';
            $script[] = '    };';
        }
        else
        {
            $script[] = '    options["progressall"] = ' . $options['progressall'];
        }

        /***
         * 'done' callback
         */

        $script[] = '';

        if(isset($options['done']))
        {
            $script[] = '    options["done"] = ' . $options['done'];
        }

        /**
         * 'fail' callback
         */

        $script[] = '';

        if(isset($options['fail']))
        {
            $script[] = '    options["fail"] = ' . $options['fail'];
        }

        /**
         * 'always' callback
         */

        $script[] = '';

        if(isset($options['always']))
        {
            $script[] = '    options["always"] = ' . $options['always'];
        }

        /***/

        $script[] = '';
        $script[] = '    ChunkedFileUpload.initialize(options);';
        $script[] = '});';

        /*************************/

        $script = $this->Html->scriptBlock(implode("\n", $script), ['block' => $options['block']]);
		if ($options['block'] === false) {
			$html .= PHP_EOL . $script;
		}

        return $html;
    }
}
