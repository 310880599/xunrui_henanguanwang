    <script>
    
    
            function browserRedirect() {
                
                var sUserAgent = navigator.userAgent.toLowerCase();
                
                var isMobile = /mobile|android|iphone|ipad|ipod|blackberry|iemobile|opera mini|windows phone/i.test(sUserAgent);
        
                if (isMobile) {
                    // 移动端代码
                    //console.log("移动设备");

                                                
                            // 在HTML开始处添加立即执行的rem计算
                            
                            (function() {
                                function updateRem() {
                                    var docEl = document.documentElement;
                                    var clientWidth = docEl.clientWidth;
                                    if (!clientWidth) return;
                                    if (clientWidth >= 640) {
                                        docEl.style.fontSize = '100px';
                                    } else {
                                        docEl.style.fontSize = 100 * (clientWidth / 640) + 'px';
                                    }
                                }
                                
                                // 立即执行一次
                                updateRem();
                                
                                // DOMContentLoaded时再次执行
                                document.addEventListener('DOMContentLoaded', updateRem);
                                
                                // 窗口大小改变时执行
                                window.addEventListener('resize', updateRem);
                            })();
                            
                            
                                
                    
                } else {
                    // PC端代码
                    // 您的PC端代码
                    
                                
                    

                                                    			
                                // PC端rem计算，立即执行
                                (function() {
                                    function updateRem() {
                                        var designSize = 1920; // 设计图尺寸
                                        var docEl = document.documentElement;
                                        var clientWidth = docEl.clientWidth; // 窗口宽度
                                        if (!clientWidth) return;
                                        var rem = clientWidth * 100 / designSize;
                                        docEl.style.fontSize = rem + 'px';
                                    }
                                    
                                    // 立即执行一次
                                    updateRem();
                                    
                                    // DOMContentLoaded时再次执行
                                    document.addEventListener('DOMContentLoaded', updateRem);
                                    
                                    // 窗口大小改变时执行，添加防抖
                                    var tid;
                                    window.addEventListener('resize', function() {
                                        clearTimeout(tid);
                                        tid = setTimeout(updateRem, 1);
                                    }, false);
                                    
                                    // 处理从缓存恢复页面的情况
                                    window.addEventListener('pageshow', function(e) {
                                        if (e.persisted) {
                                            clearTimeout(tid);
                                            tid = setTimeout(updateRem, 1);
                                        }
                                    }, false);
                                })();

                    
                    
                    
                }
            }
            
            browserRedirect();
        
        
    </script>