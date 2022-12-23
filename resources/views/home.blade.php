@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-6 m-2">
            @foreach($tree[$id]["path"] as $item)
                <a href="/home/{{$item}}/">{{$tree[$item]["key"]}}</a>
            @endforeach
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <ul>
                    @foreach($tree as $key => $el)
                        @if($key == $id)
                            <li>  @for($i =1; $i<= count($el["path"]); $i++)
                                    -
                                @endfor
                                <b>{{$el["key"]}}</b>
                            </li>
                        @else
                            <li>
                                @for($i =1; $i<= count($el["path"]); $i++)
                                    -
                                @endfor
                                <a href="/home/{{$key}}"> {{$el["key"]}}</a></li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-md-7">
            @if($sectionIsset != 0)
                <div class="row mb-4">
                    @foreach($tree[$id] as $key => $el)
                        @if(isset($el["key"]))
                            <div class="card col-2 p-3">
                                @if(isset($sectionsDetail[$key]["prop"]["img"]))
                                    <img src="{{$sectionsDetail[$key]["prop"]["img"]}}" class="card-img-top" alt="...">
                                @endif
                                <a href="/home/{{$key}}">
                                    <span>{{$el["key"]}}</span>
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="row">
                <ul>
                    @if (!empty($tree[$id]["elements"]))
                        @foreach($tree[$id]["elements"] as $key => $el)
                            @if(isset($el["name"]) && empty($el["prop"]["is_op"]))
                                <li><a href="/detail/{{$el["id"]}}">{{$el["name"]}}</a>
                                    <ul>
                                        @foreach($el["prop"] as $key => $prop)
                                            @if(is_array($prop))
                                                <li>{{$key}}</li>
                                                <select>
                                                    @foreach($prop as $key => $prop)
                                                        <option>{{$prop}}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <li>{{$key}} - {{$prop}}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </li>
                            @endif
                        @endforeach
                    @endif
                </ul>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <ul>
                    @foreach($allProps as $prop)
                        @if (!empty($prop->propvalue))

                            <li>
                                {{$prop->name}}
                                <ul>
                                    @if(!$prop->is_number)
                                        @foreach($prop->propvalue as $value)
                                            <li>
                                                <div>
                                                    <input type="checkbox" name="{{$value->id}}">
                                                    <label for="scales">{{$value->value}}</label>
                                                </div>
                                            </li>
                                        @endforeach
                                    @else
                                        <li>
                                            <div>
                                                <input type="range" id="volume" name="volume"
                                                       min="{{$prop->propvalue["min"]}}" max="{{$prop->propvalue["max"]}}">
                                                <label for="volume">{{$prop->propvalue["max"]}}</label>
                                            </div>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
