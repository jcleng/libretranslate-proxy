FROM registry.cn-hangzhou.aliyuncs.com/jcleng/library-python:3.11-slim

WORKDIR /app

# 设置环境变量
ENV PYTHONUNBUFFERED=1
ENV LIBRE_URL=http://libretranslate:5000

# 复制 Python 脚本到镜像内
COPY translate_proxy.py /app/translate_proxy.py

# 安装 Python 依赖（使用清华镜像源加速）
RUN pip install --no-cache-dir -i https://pypi.tuna.tsinghua.edu.cn/simple \
    flask requests flask-cors

# 暴露端口（50116 代理之后的端口）
EXPOSE 50116

# 启动命令
CMD ["python", "translate_proxy.py"]