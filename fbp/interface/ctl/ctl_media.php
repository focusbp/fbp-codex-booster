<?php

interface ctl_media {

	/**
	 * Displays a PDF using a Smarty template. The template format is specified in the documentation. 
	 * This function opens a dialog window where the PDF is displayed.
	 *
	 * @param string $pdf_template Specify the file name (including the .tpl extension) of the template located in the Templates folder of each class.
	 * @param string $download_filename Specify the file name when downloading the PDF.
	 * @param string $title Specify the title of the dialog window. Default is "Print".
	 * @param int $width Specify the width of the dialog window in pixels. Default is 600.
	 * @return void
	 */
	function show_pdf($pdf_template, $download_filename, $title = "Print", $width = 600);
	
	function save_pdf($pdf_template, $pdf_filename);

	/**
	 * Outputs an image as a response.
	 *
	 * @param string $subdir The subdirectory where the image is saved.
	 * @param string $filename The name of the image file to output.
	 * @param string|null $class The class name associated with the image. Optional.
	 * @return void
	 */
	function res_image($subdir, $filename, $class = null, $cache = true, $maxAge = 3600, $immutable = false);

	/**
	 * Outputs a previously saved image as a response.
	 *
	 * @param string $filename The name of the saved image file.
	 * @return void
	 */
	function res_saved_image($filename, $cache = true, $maxAge = 3600, $immutable = false);

	/**
	 * Resizes a saved image.
	 *
	 * @param string $inputfile The file path of the input image.
	 * @param string $outputfile The file path where the resized image will be saved.
	 * @param int $width The width to resize the image to.
	 * @param int $quality The quality of the output image. Default is 100.
	 * @return void
	 */
	function resize_saved_image($inputfile, $outputfile, $width, $quality = 100);

	/**
	 * Outputs the data of a saved file as a response.
	 *
	 * @param string $filename The name of the saved file.
	 * @return void
	 */
	function res_saved_file($filename, $download_name = null);

	/**
	 * Outputs a CSV response.
	 *
	 * @param array $row_arr The array of rows to be included in the CSV.
	 * @param string $encode The encoding format. Default is "sjis-win".
	 * @param string $ret The line break format. Default is "\r\n".
	 * @param string $quote The quote character used to wrap fields. Default is an empty string.
	 * @return void
	 */
	function res_csv($row_arr, $encode = "sjis-win", $ret = "\r\n", $quote = "");

	/**
	 * Closes a multi-dialog window.
	 *
	 * @param string $dialog_name The name of the dialog to close.
	 * @param string|null $class The class name associated with the dialog. Optional.
	 * @return void
	 */
	function close_multi_dialog($dialog_name, $class = null);

	/**
	 * Sends an email with the provided content and optional attachments.
	 *
	 * @param string $to The recipient's email address.
	 * @param string $subject The subject of the email.
	 * @param string $body The body of the email.
	 * @param array|null $attachment_files An array of file paths to attach to the email. Optional.
	 * @return void
	 */
	function send_mail_text($to, $subject, $body, $attachment_files = null);

	/**
	 * Sends an email based on a predefined format. The email content is prepared according to the specified format.
	 *
	 * @param string $to The recipient's email address.
	 * @param string $format_key The key to identify the email format.
	 * @param array|null $attachment_files An array of files to attach to the email. Optional.
	 * @return void
	 */
	function send_mail_prepared_format($to, $format_key, $attachment_files = null);

	/**
	 * Retrieves the email body based on the specified format key.
	 *
	 * @param string $format_key The key to identify the email format.
	 * @return string The email body.
	 */
	function get_mail_body_prepared_format($format_key);

	/**
	 * Retrieves the thumbnail image of a Vimeo video.
	 *
	 * @param int $vimeo_id The ID of the Vimeo video.
	 * @return string|null The URL of the video thumbnail, or null if not found.
	 */
	function get_vimeo_thumbnail($vimeo_id);

	/**
	 * Deletes a Vimeo video using its ID.
	 *
	 * @param int $vimeo_id The ID of the Vimeo video to delete.
	 * @return void
	 */
	function delete_vimeo($vimeo_id);

	/**
	 * Generates QR code PNG binary data for in-memory usage.
	 *
	 * @param string $text The text to be encoded into the QR code.
	 * @param string $level Error correction level.
	 * @param int $size Pixel size.
	 * @param int $margin Margin size.
	 * @return string PNG binary data.
	 */
	function qrcode_text_binary($text, $level = 'L', $size = 3, $margin = 4);

	/**
	 * Generates a Google Calendar event link based on the provided details.
	 *
	 * @param int|string $timestamp_start The start time of the event as a timestamp or string.
	 * @param int|string $timestamp_end The end time of the event as a timestamp or string.
	 * @param string $title The title of the event.
	 * @param string $description A description of the event.
	 * @param string $location The location of the event. Optional.
	 * @param string $timezone The timezone for the event. Optional.
	 * @return string The URL link to create a Google Calendar event.
	 */
	function google_calendar_link($timestamp_start, $timestamp_end, $title, $description, $location = "", $timezone = "");

	/**
	 * Converts text to speech using Google's Text-to-Speech API.
	 *
	 * @param string $text The text to be converted to speech.
	 * @param string $filename filename as saving sound file.
	 * @param string $lang The language code for the speech. Default is 'en-US'.
	 * @param string $voice The specific voice to be used for the speech. Optional.
	 * @param float $pitch The pitch of the speech. Default is 1.
	 * @param float $speed The speaking rate of the speech. Default is 1.
	 * @return string|bool The file name of the generated speech audio file, or false if the conversion fails.
	 */
	function text_to_speech($text, $filename_mp3, $lang = 'en-US', $voice = '', $pitch = 1, $speed = 1);

	/**
	 * Translates a given string from the source language to the target language using Google Translate API.
	 *
	 * @param string $q The text to be translated.
	 * @param string $language_source The source language code. Default is 'ja' (Japanese).
	 * @param string $language_target The target language code. Default is 'en' (English).
	 * @return string The translated text.
	 */
	function translate($q, $language_source = "ja", $language_target = "en");

	/**
	 * Resets the ChatGPT conversation history.
	 *
	 * @return void
	 */
	function chatGPT_reset_history();

	/**
	 * Adds a new message to the ChatGPT conversation history.
	 *
	 * @param string $role The role of the message (e.g., 'user', 'assistant', etc.).
	 * @param string $prompt_or_smartytemplate The message content or a Smarty template to fetch the content from.
	 * @return void
	 */
	function chatGPT_add_history($role, $prompt_or_smartytemplate);

	/**
	 * Retrieves the ChatGPT conversation history.
	 *
	 * @return array An array representing the ChatGPT conversation history.
	 */
	function chatGPT_get_history(): array;

	/**
	 * Sends a prompt to ChatGPT and retrieves the response.
	 *
	 * @param string $prompt_or_smartytemplate The prompt text or a Smarty template to fetch the prompt from.
	 * @param string $role The role of the message, default is 'user'.
	 * @param float $temperature The sampling temperature for the response. Default is 0.
	 * @param int $tokens The maximum number of tokens for the response. Default is 1000.
	 * @param string $model The model to be used, default is 'gpt-4'.
	 * @return string The response generated by ChatGPT.
	 */
	function chatGPT($prompt_or_smartytemplate, $role = "user", $temperature = 0, $tokens = 1000, $mode = "api");

	/**
	 * Creates a new Chart instance for drawing charts.
	 *
	 * @return \chartjs\Chart An instance of the Chart class.
	 */
	function create_chart(): \chartjs\Chart;

	/**
	 * Draws a chart on the specified canvas using the provided Chart instance.
	 *
	 * @param string $canvas_tag_id The ID of the canvas element in the template where the chart will be drawn.
	 * @param \chartjs\Chart $chart An instance of the Chart class that is already configured.
	 * @return void
	 */
	function chart_draw($canvas_tag_id, \chartjs\Chart $chart);

	/**
	 * Creates a new instance of the OpenAI assistant-related object.
	 *
	 * @return \openai\OpenAI An instance of the OpenAI class.
	 */
		function create_openai($model, $assistant,$base_instruction="", ?\openai\Recorder $message_recorder=null, ?\openai\StatusManager $status_manager=null,$network_logger=null): \openai\OpenAI_class;
	
	function openai_get_assistant();
	
	function create_vimeo():Vimeo;
	
	function create_linebot(): linebot;
	
	function create_pdfmaker(): pdfmaker_class;

	function create_ValueFormatter(): ValueFormatter;
	
	function cron_set();
}
