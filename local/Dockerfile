# libretranslate-local
FROM registry.cn-hangzhou.aliyuncs.com/jcleng/libretranslate-libretranslate

ENV LT_LOAD_ONLY=en,zh

WORKDIR /app/

RUN /app/venv/bin/python ./venv/bin/libretranslate \
    --load-only ${LT_LOAD_ONLY} \
    --update-models \
    || echo "Models downloaded or already exist"

EXPOSE 5000
