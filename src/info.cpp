#include "info.h"
#include "types.h"
#include "valueparser.h"
#include "parsers.h"

#include <boost/spirit/include/phoenix.hpp>

#include <fstream>
#include <iostream>

using namespace boost::spirit::qi;
using namespace boost::phoenix;
using namespace sc2replay::parsers;


namespace sc2replay
{
    
    Info::Info()
    {
    }

    Info::~Info()
    {
    }

    using sc2replay::parsers::string;

    void
    Info::load(const uint8_t* begin, const uint8_t* end)
    {
        parse(begin, end,
              omit[repeat(6)[byte_]] >> players >> string, //ignoring minimapName for now
              players_, mapName_);
    }

    const Players& Info::getPlayers() const
    {
        return players_;
    }

    const uint8_t Info::getNumberOfPlayers() const
    {
        return players_.size();
    }

    const std::string& Info::getMapFilename() const
    {
        return mapFilename_;
    }

    const std::string& Info::getMapName() const
    {
        return mapName_;
    }

    void Info::exportDump( const std::string& filename ) const
    {
        std::ofstream file( filename.c_str(), std::ios::binary );
        //TODO file.write( (const char*)buffer_, bufferSize_ );
        file.close();
    }
}

// Local Variables:
// mode:c++
// c-file-style: "stroustrup"
// end:

