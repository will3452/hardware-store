PYESIO

{!! $messageData['title'] !!}

{!! $messageData['body'] !!}

@if(!empty($messageData['action_url']))
{!! $messageData['action_text'] !!}: {!! $messageData['action_url'] !!}
@endif
@if(!empty($messageData['note']))
{!! $messageData['note'] !!}
@endif

Pyesio · Hardware business, made simple.
This is an automated account or service message.
