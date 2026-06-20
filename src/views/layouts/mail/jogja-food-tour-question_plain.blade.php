
<!DOCTYPE html>
<html>
<head>
    <title>Jogja Food Tour</title>
</head>
<body>

<p>Hi {{$data->name}} 👋,</p>

<p>Just a friendly reminder that your food tour is scheduled for <b>{{ $data->time_description }}</b>.</p>


<p>📅 Date: <b>{{ $data->date }}</b></p>
<p>🕒 Time: <b>{{ $data->time }}</b></p>
<p>📍 Meeting Point: <b>{{ $data->location }}</b></p>
<p>🗺️ Maps: <b>{{ $data->map }}</b></p>
<p><i>{{$data->map_description}}</i></p>


<p>{{$data->question}}</p>

<p>We look forward to welcoming you soon! 🙏😊</p>


</body>
</html>