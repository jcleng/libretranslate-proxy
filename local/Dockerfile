# libretranslate-local
FROM registry.cn-hangzhou.aliyuncs.com/jcleng/libretranslate-libretranslate

ENV LT_LOAD_ONLY=en,zh

WORKDIR /app/

RUN /app/venv/bin/argospm install translate-en_zh && /app/venv/bin/argospm install translate-zh_en && /app/venv/bin/argospm list

EXPOSE 5000
