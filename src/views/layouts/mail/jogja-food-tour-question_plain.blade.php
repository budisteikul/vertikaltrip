
<!DOCTYPE html>
<html>
<head>
    <title>Jogja Food Tour</title>
</head>
<body>
    <p>Hello {{$data->name}} 👋,</p>
    <p>Thank you for booking our food tour .</p>
    <p>The food tour will start {{ $data->time_description }} at {{ $data->time }} and our meeting point is at {{ $data->location }}.</p>
    <p>Map : {{$data->map}}</p>
    <p>{{$data->map_description}}</p>
    <p>By the way, do you/does anyone in your group have any food allergy or dietary restrictions?</p>
</body>
</html>