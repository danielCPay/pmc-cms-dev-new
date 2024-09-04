<?php

/**
 * TestModuleWorkflow.
 *
 * @package   Workflow
 *
 * @copyright DOT Systems
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Michał Kamiński <mkaminski@dotsystems.pl>
 */

class TestModuleWorkflow
{
	public static function testMondayCom(Vtiger_Record_Model $recordModel)
	{
		$id = $recordModel->getId();
		$itemId = $recordModel->get('monday_item_id');
		$boardId = 6739621895;
		$authToken = \App\Config::api('MONDAY_COM_AUTH_TOKEN');

		\App\Log::warning("TestModule::Workflows::testMondayCom:$id/$itemId");

		$query = 'mutation ($itemId: ID!, $boardId: ID!) { change_multiple_column_values(item_id: $itemId, board_id: $boardId, column_values: "{\"text4\" : \"DOTS API TEST\"}") { id } }';
		$variables = [ 'itemId' => $itemId, 'boardId' => $boardId];

		$url = 'https://api.monday.com/v2';
		$body = ['json' => ['query' => $query, 'variables' => $variables]];
		$headers = ['Authorization' => $authToken];

		$options = array_merge(\App\RequestHttp::getOptions(), ['headers' => $headers]);
		$client = (new \GuzzleHttp\Client($options));
		$request = $client->request('POST', $url, $body);
		
		if (200 !== $request->getStatusCode()) {
			throw new \App\Exceptions\AppException('Error occurred on Monday.com call: ' . $request->getReasonPhrase());
		} else {
			$responseBody = $request->getBody();
			\App\Log::warning("TestModule::Workflows::testMondayCom:$responseBody");
			$response = \App\Json::decode($responseBody);

			// $response may contain 'errors', 'error_code', 'error_message' keys, throw new error with messages if needed
			if (isset($response['errors'])) {
				$errors = array_map(function($row) { return $row['message']; }, $response['errors']);
			} else if (isset($response['error_code'])) {
				$errors = [$response['error_code'] . ': ' . $response['error_message']];
			} else if (isset($response['error_message'])) {
				$errors = [$response['error_message']];
			}
			
			if (!empty($errors)) {
				$errorMessage = 'Errors occurred on Monday.com call:\n' . implode("\n- ", $errors);
				throw new \App\Exceptions\AppException($errorMessage);
			}
		}
	}
}
