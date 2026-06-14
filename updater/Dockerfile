FROM python:3.12-slim

RUN apt-get update && \
    apt-get -y dist-upgrade && \
    apt-get -y install monitoring-plugins-basic psmisc && \
    apt-get -y autoremove && \
    apt-get -y purge $(dpkg -l | grep '^rc' | awk '{print $2}') && \
    apt-get clean

RUN pip install --no-cache-dir pymysql pymemcache phpserialize
RUN mkdir /opt/torstatus

ADD tns_update.py /opt/torstatus
ADD updater.sh /opt/torstatus

RUN addgroup --gid 1026 updater && adduser --disabled-password --ingroup updater --system updater && chown -R updater:updater /opt/torstatus
USER updater

CMD [ "/opt/torstatus/updater.sh" ]

