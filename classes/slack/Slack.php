<?php

/**
 *
 *
 * @author Vladimir Minkovich
 * @date 14.01.17
 * @time 1:37
 */
class Slack
{
    const URL = 'https://hooks.slack.com/services/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';

    public function sendNotification($content, $applicationName)
    {
        $handle = curl_init(self::URL);

        $attachments = $content;

        $data = array(
            'text' => '*' . $applicationName . '*',
            'attachments' => $attachments
        );

        $data_string = json_encode($data);


        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($handle);

        curl_close($handle);

        return $result;
    }


}