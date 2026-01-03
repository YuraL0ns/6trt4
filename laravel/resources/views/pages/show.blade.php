@extends('layouts.app')

@section('title', ($page->page_title ?? 'Страница') . ' - Hunter-Photo.Ru')

@section('content')
    <div class="max-w-4xl mx-auto">
        <x-card>
            <h1 class="text-3xl font-bold text-white mb-6">{{ $page->page_title }}</h1>
            
            <div class="prose prose-invert max-w-none">
                {!! $page->page_content !!}
            </div>
        </x-card>
    </div>
@endsection

@push('styles')
<style>
.prose {
    color: #e5e7eb;
}
.prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
    color: #ffffff;
    font-weight: 700;
    margin-top: 2em;
    margin-bottom: 1em;
}
.prose h1 {
    font-size: 2.25em;
}
.prose h2 {
    font-size: 1.875em;
}
.prose h3 {
    font-size: 1.5em;
}
.prose p {
    margin-bottom: 1.25em;
    line-height: 1.75;
}
.prose ul, .prose ol {
    margin-bottom: 1.25em;
    padding-left: 1.625em;
}
.prose li {
    margin-bottom: 0.5em;
}
.prose a {
    color: #a78bfa;
    text-decoration: underline;
}
.prose a:hover {
    color: #8b5cf6;
}
.prose strong {
    color: #ffffff;
    font-weight: 700;
}
.prose em {
    font-style: italic;
}
</style>
@endpush

