@extends('pdf.layouts.document')

@section('title', 'Prueba')
@section('doc_title', 'DOCUMENTO DE PRUEBA')
@section('doc_number', 'PR-000001')

@section('content')
    <div class="section-title">Detalle</div>
    <p>Texto con tildes y ñ: Cañón, camión, año.</p>
@endsection
