@extends('backend.layout.master')

@section('element')
<div class="row">

    <div class="col-12 col-md-12 col-lg-12">
        <div class="card">

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table" id="myTable">
                        <thead>
                            <tr>
                                <th>{{ __('Theme') }}</th>
                                <th>{{ __('Previw') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <h5>

                                        {{ __('Default Theme') }}
                                    </h5>
                                    <p>
                                        <a data-route="{{ route('admin.manage.theme.update', 'default') }}" data-color="#9c0ac" class="@if (Config::config()->theme != 'default') btn btn-outline-danger btn-sm active-btn @endif  @if (Config::config()->theme == 'default') text-success @else text-danger @endif font-weight-bolder">
                                            @if (Config::config()->theme == 'default')
                                            {{ __('Activated') }}
                                            @else
                                            {{ __('Active') }}
                                            @endif
                                        </a>
                                    </p>
                                </td>
                              
                                <td>
                                    <button data-href="https://signalmax.springsoftit.com/" class="btn btn-primary btn-sm prev">
                                        {{ __('Preview') }}
                                    </button>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <h5>

                                        {{ __('Light Theme') }}
                                    </h5>
                                    <p>
                                        <a data-route="{{ route('admin.manage.theme.update', 'light') }}" data-color="#F2062F" class="@if (Config::config()->theme != 'light') btn btn-outline-danger btn-sm active-btn @endif  @if (Config::config()->theme == 'light') text-success @else text-danger @endif font-weight-bolder" >
                                            @if (Config::config()->theme == 'light')
                                            {{ __('Activated') }}
                                            @else
                                            {{ __('Active') }}
                                            @endif
                                        </a>
                                    </p>
                                </td>
                                
                                <td>
                                    <button data-href="https://signalmax.springsoftit.com/" class="btn btn-primary btn-sm prev">
                                        {{ __('Preview') }}
                                    </button>
                                </td>
                            </tr>


                            <tr>
                                <td>
                                    <h5>

                                        {{ __('Blue Theme') }}
                                    </h5>
                                    <p>
                                        <a data-route="{{ route('admin.manage.theme.update', 'blue') }}" data-color="#0099FA" class="@if (Config::config()->theme != 'blue') btn btn-outline-danger btn-sm active-btn @endif  @if (Config::config()->theme == 'blue') text-success @else text-danger @endif font-weight-bolder">
                                            @if (Config::config()->theme == 'blue')
                                            {{ __('Activated') }}
                                            @else
                                            {{ __('Active') }}
                                            @endif
                                        </a>
                                    </p>
                                </td>
                               
                                <td>
                                    <button data-href="https://signalmax.springsoftit.com/" class="btn btn-primary btn-sm prev">
                                        {{ __('Preview') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modal -->
<div class="modal fade" id="activeTheme" tabindex="-1" role="dialog" aria-labelledby="modelTitleId" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="" method="post">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Active Template') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid">
                        <input type="hidden" name="theme">
                        <input type="hidden" name="color">
                        {{ __('Are you sure to active this template ?') }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Active') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Modal -->
<div class="modal fade" id="prev" tabindex="-1" role="dialog" aria-labelledby="modelTitleId" aria-hidden="true">
    <div class="modal-dialog modal--w" role="document">

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Template Preview') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <iframe src="" frameborder="0" id="iframe"></iframe>
            </div>

        </div>

    </div>
</div>
@endsection

@push('script')
<style>
    .modal-dialog.modal--w {
        max-width: 96% !important;
    }

    #iframe {
        width: 100%;
        height: 100vh;
    }


    .sp-replacer {
        padding: 0;
        border: 1px solid rgba(0, 0, 0, .125);
        border-radius: 5px 0 0 5px;
        border-right: none;
    }

    select.form-control:not([size]):not([multiple]) {
        height: calc(2.25rem + 9px);
    }

    .sp-preview {
        width: 100px;
        height: 46px;
        border: 0;
    }

    .sp-preview-inner {
        width: 110px;
    }

    .sp-dd {
        display: none;
    }
</style>
@endpush
@push('external-style')
<link rel="stylesheet" href="{{ Config::cssLib('backend', 'bootstrap-colorpicker.min.css') }}">
@endpush

@push('external-script')
<script src="{{ Config::jsLib('backend', 'bootstrap-colorpicker.min.js') }}"></script>
@endpush

@push('script')
<script>
    $(function() {
        'use strict'
       
        $('.active-btn').on('click', function() {
            const modal = $('#activeTheme');

            modal.find('form').attr('action', $(this).data('route'))

            modal.find('input[name=theme]').val($(this).data('theme'))
            
            modal.find('input[name=color]').val($(this).data('color'))

            modal.modal('show')
        })


        $('.prev').on('click', function() {
            const modal = $('#prev');

            modal.find('iframe').attr('src', $(this).data('href'))

            modal.modal('show')
        })
    })
</script>
@endpush