<?php

namespace app\common\exceptions;

use app\common\traits\JsonTrait;
use app\common\traits\MessageTrait;
use EasyWeChat\Core\Exceptions\HttpException;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class Handler extends ExceptionHandler
{
    use JsonTrait;
    use MessageTrait;
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        ShopException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
        \EasyWeChat\Core\Exceptions\HttpException::class,
        \EasyWeChat\Core\Exceptions\InvalidArgumentException::class,
        NotFoundException::class,
        MemberNotLoginException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     * @param Exception $exception
     * @throws Exception
     */
    public function report(Exception $exception)
    {
        if ($this->shouldntReport($exception)) {
            return;
        }
        try{
            // 记录错误日志
            if(!app()->runningInConsole()){
                \Log::error('http parameters',request()->input());
            }
            \Log::error($exception);
        }catch (Exception $ex){
            dump($ex);
        }

        // 生产环境发送邮件
//        if(app()->environment() == 'production'){
//            Mail::to('shenyang@yunzshop.com')->send(new \App\Mail\ErrorReport('错误',$exception));
//        }
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception $exception
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Exception $exception)
    {
        if (DB::logging()) {
            \Log::debug('错误sql记录', array_map(function ($query) {
                $result = str_replace(array('%', '?'), array('%%', '%s'), $query['query']);
                $result = vsprintf($result, $query['bindings']);
                return $result;
            }, DB::getQueryLog()));
        }

        // 商城异常
        if ($exception instanceof ShopException) {
            return $this->renderShopException($exception);
        }
        // 404
        if ($exception instanceof NotFoundException) {
            return $this->renderNotFoundException($exception);

        }

        //开发模式异常
        if (config('app.debug')) {
            return $this->renderExceptionWithWhoops($exception);
        }
        //api异常
        if (\YunShop::isApi()) {
            return $this->errorJson($exception->getMessage());
        }
        //默认异常
        if ($this->isHttpException($exception)) {
            return $this->renderHttpException($exception);
        }
        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Auth\AuthenticationException $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest('login');
    }

    protected function renderShopException(ShopException $exception)
    {
        if (\Yunshop::isApi() || request()->ajax()) {
            return $this->errorJson($exception->getMessage(), $exception->getData());
        }
        $redirect = $exception->redirect ?: '';
        exit($this->message($exception->getMessage(), $redirect, 'error'));
    }

    /**
     * Render an exception using Whoops.
     *
     * @param  \Exception $e
     * @return \Illuminate\Http\Response
     */
    protected function renderExceptionWithWhoops(Exception $e)
    {
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());

        return new \Illuminate\Http\Response(
            $whoops->handleException($e),
            $e->getStatusCode(),
            $e->getHeaders()
        );
    }

    protected function renderNotFoundException(NotFoundException $exception)
    {
        if (\Yunshop::isPHPUnit()) {

            exit($exception->getMessage());
        }
        if (\Yunshop::isApi() || request()->ajax()) {
            return $this->errorJson($exception->getMessage());
        }

        abort(404, $exception->getMessage());

    }
}
