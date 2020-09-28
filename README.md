# co2
PHP5 JSONP service delivering weekly CO2 averages from NOAA in parts per million. All it does is extract the values from the entry in [this web feed](http://www.esrl.noaa.gov/gmd/webdata/ccgg/trends/rss.xml) corresponding to the latest weekly average. The XML file of the feed is fetched only once a day by default to guarantee that the bandwidth party can go on for the folks at ESRL/NOAA. Not that it is a huge file but just in case.

## Why bother?
As expected, NOAA does provide a web service for experimental data from its Global Monitoring Laboratory on Mauna Loa (Hawaii). However, it's not granular enough to give you just CO2 concentrations. This service script aims to cater those users that just want those concentrations and tries to do so in a more plug-and-play fashion. 

## Response object
Being a JSONP service, a callback parameter is expected. For instance, if the service is hosted at `http://www.climate.org/co2` a request to `http://www.climate.org/co2?callback=process` should yield the following response:
```javascript
process({
  "0":"411.00",
  "1":"408.34",
  "10":"386.81",
  "units":"ppm",
  "date":"2020-09-27T13:00:56+02:00",
  "delta":5.56,
  "all":"Up-to-date weekly average CO2 at Mauna Loa\nWeek starting on September 20, 2020: 411.00 ppm\nWeekly value from 1 year ago: 408.34 ppm\nWeekly value from 10 years ago: 386.81 ppm"
})
```
What follows is a description of each of the JSON object's properties:
- `0` Latest weekly average
- `1` Daily average approx. 1 year ago
- `10` Daily average approx. 10 years ago
- `units` Units in which the averages are expressed
- `date` ISO-8601 date of publication of the averages
- `delta` Estimated percentage change between historical daily averages (9 years)
- `all` Human-readable summary of all three averages

## Endpoints
Endpoints giving plain text responses are also supported. Using the same sample URL as in the previous section, these would be:
- `http://www.climate.org/co2/[0|1|10]` Default response is the daily average.
- `http://www.hqcasanova.com/co2/delta`
- `http://www.hqcasanova.com/co2/all`
- `http://www.hqcasanova.com/co2/help`

## JSONP client code
```javascript
function fn (data) {
    //Fiddle with JSON data
}

var script = document.createElement('script');
script.src = 'http://www.hqcasanova.com/co2?callback=fn';

document.getElementsByTagName('head')[0].appendChild(script);
```
