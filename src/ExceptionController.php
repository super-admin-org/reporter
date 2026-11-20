<?php

namespace SuperAdmin\Admin\Reporter;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use SuperAdmin\Admin\Controllers\ModelForm;
use SuperAdmin\Admin\Facades\Admin;
use SuperAdmin\Admin\Grid;
use SuperAdmin\Admin\Layout\Content;
use SuperAdmin\Admin\Reporter\Tracer\Parser;

class ExceptionController
{
    use ModelForm;

    /**
     * Index interface.
     */
    public function index(): Content
    {
        return Admin::content(function (Content $content) {
            $content->header('Exception');
            $content->description('Exception list');

            $content->body($this->grid());
        });
    }

    public function grid(): Grid
    {
        return Admin::grid(ExceptionModel::class, function (Grid $grid) {
            $grid->model()->orderBy('id', 'desc');

            $grid->column('id', 'ID')->sortable();

            $grid->column('type')->display(function ($type) {
                return '<span title="'.e($type).'">'.e(class_basename((string) $type)).'</span>';
            });

            $grid->column('code');

            $grid->column('message')->style('width:400px')->display(function ($message) {
                if ($message === null || $message === '') {
                    return '';
                }

                return '<code title="'.e($message).'">'.e(Str::limit($message, 200)).'</code>';
            });

            $grid->column('request')->display(function () {
                return sprintf(
                    '<span class="badge bg-%s me-2">%s</span><code>%s</code>',
                    ExceptionModel::methodColor($this->method),
                    e($this->method),
                    e($this->path)
                );
            });

            $grid->column('seen', 'Seen')->display(function () {
                $count = $this->occurrences();

                return $count > 1
                    ? '<span class="badge bg-danger" title="Same exception at the same file and line">&times;'.$count.'</span>'
                    : '<span class="badge bg-light text-dark">&times;1</span>';
            });

            $grid->column('created_at');

            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->like('type');
                $filter->like('message');
                $filter->like('path');
                $filter->equal('method')->select(array_combine(array_keys(ExceptionModel::$methodColor), array_keys(ExceptionModel::$methodColor)));
                $filter->between('created_at')->datetime();
            });

            $grid->disableCreateButton();

            $grid->actions(function (Grid\Displayers\Actions\Actions $actions) {
                $actions->disableEdit();
            });
        });
    }

    public function show($id)
    {
        Reporter::importAssets();

        return Admin::content(function (Content $content) use ($id) {
            $content->header('Exception');
            $content->description('Exception detail');

            Admin::script('if (window.Prism) { Prism.highlightAll(); }');

            $exception = ExceptionModel::findOrFail($id);
            $trace = "#0 {$exception->file}({$exception->line})\n";
            $frames = (new Parser($trace.$exception->trace))->parse();
            $cookies = json_decode((string) $exception->cookies, true) ?: [];
            $headers = json_decode((string) $exception->headers, true) ?: [];
            $occurrences = $exception->occurrences();

            array_pop($frames);

            $content->body(view('super-admin-reporter::exception', compact('exception', 'frames', 'cookies', 'headers', 'occurrences')));
        });
    }

    public function destroy($id): JsonResponse
    {
        $ids = explode(',', $id);

        if (ExceptionModel::query()->whereIn('id', $ids)->delete()) {
            return response()->json([
                'status'  => true,
                'message' => trans('admin.delete_succeeded'),
            ]);
        }

        return response()->json([
            'status'  => false,
            'message' => trans('admin.delete_failed'),
        ]);
    }
}
