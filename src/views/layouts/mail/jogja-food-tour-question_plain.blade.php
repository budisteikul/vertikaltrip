
<!DOCTYPE html>
<html>
<head>
    <title>Jogja Food Tour</title>
</head>
<body>

<p>Hi {{$data->name}} 👋,</p>

<p>Just a friendly reminder that your food tour is scheduled for <b>{{ $data->time_description }}</b>.</p>

<p>
<div>📅 Date: <b>{{ $data->date }}</b></div>
<div>🕒 Time: <b>{{ $data->time }}</b></div>
<div>📍 Meeting Point: <b>{{ $data->location }}</b></div>
<div>🗺️ Maps: <b>{{ $data->map }}</b></div>
<div><i>{{$data->map_description}}</i></div>
</p>

<p>{{$data->question}}</p>

<p>We look forward to welcoming you soon! 🙏😊</p>


</body>
</html>