<?php



function sendResponse($data, $message = 'Success', $code = 200) {
    return response()->json([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'error' => null,
    ], $code);
}

function sendError($message, $code = 400, $error = []) {
    return response()->json([
        'success' => false,
        'message' => $message,
        'data' => null,
        'error' => $error,
    ], $code);
}
